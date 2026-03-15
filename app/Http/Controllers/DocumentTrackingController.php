<?php

namespace App\Http\Controllers;

use App\Mail\DocumentRoutedMail;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentRouting;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DocumentTrackingController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $isAdmin = $user->hasAnyRole(['Administrator', 'Records']);

        $query = Document::with([
            'documentType:id,name,code,lead_time_hours',
            'creator:id,name,position',
            'currentHolder:id,name,position',
            'routings' => fn ($q) => $q->orderBy('sequence'),
            'routings.sender:id,name',
            'routings.receiver:id,name',
        ]);

        if (!$isAdmin) {
            $userId = $user->id;
            $query->where(function ($q) use ($userId) {
                $q->where('created_by', $userId)
                  ->orWhereHas('routings', fn ($r) => $r->where('sender_id', $userId)->orWhere('receiver_id', $userId));
            });
        }

        $documents = $query->latest()->get()->map(function (Document $doc) {
            $routings = $doc->routings->map(fn ($r) => [
                'id'              => $r->id,
                'sequence'        => $r->sequence,
                'sender_id'       => $r->sender_id,
                'receiver_id'     => $r->receiver_id,
                'sender'          => $r->sender?->only('id', 'name'),
                'receiver'        => $r->receiver?->only('id', 'name'),
                'status'          => $r->status,
                'received_at'     => $r->received_at?->toISOString(),
                'action_taken_at' => $r->action_taken_at?->toISOString(),
                'action_taken'    => $r->action_taken,
                'remarks'         => $r->remarks,
                'due_at'          => $r->due_at?->toISOString(),
                'forwarded_at'    => $r->forwarded_at?->toISOString(),
                'is_overdue'      => $r->is_overdue,
            ]);

            return [
                'id'             => $doc->id,
                'tracking_no'    => $doc->tracking_no,
                'subject'        => $doc->subject,
                'description'    => $doc->description,
                'is_confidential'=> $doc->is_confidential,
                'urgency'        => $doc->urgency,
                'overall_status' => $doc->overall_status,
                'document_type'  => $doc->documentType?->only('id', 'name', 'code', 'lead_time_hours'),
                'creator'        => $doc->creator?->only('id', 'name', 'position'),
                'current_holder' => $doc->currentHolder?->only('id', 'name', 'position'),
                'routings'       => $routings,
                'has_overdue'    => $routings->contains('is_overdue', true),
                'created_at'     => $doc->created_at?->toISOString(),
            ];
        });

        $documentTypes = DocumentType::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'lead_time_hours', 'description']);
        $users = User::orderBy('name')->get(['id', 'name', 'position']);

        return Inertia::render('DocumentTracking/Index', [
            'documents'     => $documents,
            'documentTypes' => $documentTypes,
            'users'         => $users,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'document_type_id' => 'required|exists:document_types,id',
            'subject'          => 'required|string|max:500',
            'description'      => 'nullable|string|max:5000',
            'is_confidential'  => 'nullable|boolean',
            'urgency'          => 'required|in:Normal,Urgent,Very Urgent',
            'receiver_id'      => 'required|exists:users,id',
            'remarks'          => 'nullable|string|max:2000',
            'attachments'      => 'nullable|array',
            'attachments.*'    => 'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif',
        ]);

        $documentType = DocumentType::findOrFail($data['document_type_id']);
        $user = Auth::user();

        // Create document (tracking_no auto-generated in model boot)
        $document = Document::create([
            'tracking_no'      => 'TEMP',
            'document_type_id' => $data['document_type_id'],
            'subject'          => $data['subject'],
            'description'      => $data['description'] ?? null,
            'is_confidential'  => $data['is_confidential'] ?? false,
            'urgency'          => $data['urgency'],
            'created_by'       => $user->id,
            'current_holder_id'=> $data['receiver_id'],
            'overall_status'   => 'In-Transit',
        ]);

        // Store attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('document-attachments', 'public');
                DocumentAttachment::create([
                    'document_id' => $document->id,
                    'file_name'   => $file->getClientOriginalName(),
                    'file_path'   => $path,
                    'mime_type'   => $file->getMimeType(),
                    'file_size'   => $file->getSize(),
                    'uploaded_by' => $user->id,
                ]);
            }
        }

        // Create first routing step
        $routing = DocumentRouting::create([
            'document_id' => $document->id,
            'sequence'    => 1,
            'sender_id'   => $user->id,
            'receiver_id' => $data['receiver_id'],
            'status'      => 'Pending',
            'remarks'     => $data['remarks'] ?? null,
            'due_at'      => now()->addHours($documentType->lead_time_hours),
        ]);

        // Notify receiver via email
        $routing->load(['document.documentType', 'sender', 'receiver']);
        try {
            Mail::to($routing->receiver->email)->send(new DocumentRoutedMail($routing, 'new'));
        } catch (\Throwable $e) {
            logger()->warning('DocumentTracking mail failed: ' . $e->getMessage());
        }

        return redirect()->route('document-tracking.index')
            ->with('success', "Document {$document->tracking_no} created and routed successfully.");
    }

    public function show(Document $document)
    {
        $user = Auth::user();
        $isAdmin = $user->hasAnyRole(['Administrator', 'Records']);

        if (!$isAdmin) {
            $userId = $user->id;
            $isParticipant = $document->created_by === $userId
                || $document->routings()->where('sender_id', $userId)->orWhere('receiver_id', $userId)->exists();
            abort_if(!$isParticipant, 403, 'You do not have access to this document.');
        }

        $document->load([
            'documentType',
            'creator:id,name,position,email',
            'currentHolder:id,name,position',
            'attachments.uploader:id,name',
            'routings' => fn ($q) => $q->orderBy('sequence'),
            'routings.sender:id,name,position',
            'routings.receiver:id,name,position',
        ]);

        $users = User::orderBy('name')->get(['id', 'name', 'position']);

        $routings = $document->routings->map(fn ($r) => [
            'id'              => $r->id,
            'sequence'        => $r->sequence,
            'sender_id'       => $r->sender_id,
            'receiver_id'     => $r->receiver_id,
            'sender'          => $r->sender?->only('id', 'name', 'position'),
            'receiver'        => $r->receiver?->only('id', 'name', 'position'),
            'status'          => $r->status,
            'received_at'     => $r->received_at?->toISOString(),
            'action_taken_at' => $r->action_taken_at?->toISOString(),
            'action_taken'    => $r->action_taken,
            'remarks'         => $r->remarks,
            'due_at'          => $r->due_at?->toISOString(),
            'forwarded_at'    => $r->forwarded_at?->toISOString(),
            'is_overdue'      => $r->is_overdue,
        ]);

        return Inertia::render('DocumentTracking/Show', [
            'document' => [
                'id'              => $document->id,
                'tracking_no'     => $document->tracking_no,
                'subject'         => $document->subject,
                'description'     => $document->description,
                'is_confidential' => $document->is_confidential,
                'urgency'         => $document->urgency,
                'overall_status'  => $document->overall_status,
                'document_type'   => $document->documentType,
                'creator'         => $document->creator?->only('id', 'name', 'position', 'email'),
                'current_holder'  => $document->currentHolder?->only('id', 'name', 'position'),
                'attachments'     => $document->attachments->map(fn ($a) => [
                    'id'        => $a->id,
                    'file_name' => $a->file_name,
                    'file_size' => $a->file_size,
                    'mime_type' => $a->mime_type,
                    'uploader'  => $a->uploader?->only('id', 'name'),
                    'created_at'=> $a->created_at?->toISOString(),
                ]),
                'routings'       => $routings,
                'created_at'     => $document->created_at?->toISOString(),
            ],
            'users' => $users,
        ]);
    }

    public function receive(DocumentRouting $routing)
    {
        abort_if(Auth::id() !== $routing->receiver_id, 403);
        abort_if($routing->status !== 'Pending', 422, 'Document already received.');

        $routing->update([
            'status'      => 'Received',
            'received_at' => now(),
        ]);

        return back()->with('success', 'Document marked as received.');
    }

    public function action(Request $request, DocumentRouting $routing)
    {
        abort_if(Auth::id() !== $routing->receiver_id, 403);
        abort_if(!in_array($routing->status, ['Pending', 'Received']), 422, 'Cannot record action on this routing step.');

        $data = $request->validate([
            'action_taken' => 'required|string|max:2000',
            'remarks'      => 'nullable|string|max:2000',
        ]);

        $routing->update([
            'status'          => 'Action Taken',
            'action_taken'    => $data['action_taken'],
            'action_taken_at' => now(),
            'remarks'         => $data['remarks'] ?? $routing->remarks,
            'received_at'     => $routing->received_at ?? now(),
        ]);

        return back()->with('success', 'Action recorded successfully.');
    }

    public function forward(Request $request, DocumentRouting $routing)
    {
        abort_if(Auth::id() !== $routing->receiver_id, 403);
        abort_if($routing->status === 'Forwarded', 422, 'Already forwarded.');

        $data = $request->validate([
            'receiver_id'  => 'required|exists:users,id',
            'action_taken' => 'nullable|string|max:2000',
            'remarks'      => 'nullable|string|max:2000',
        ]);

        $user = Auth::user();

        // Mark current step as Forwarded
        $routing->update([
            'status'          => 'Forwarded',
            'forwarded_at'    => now(),
            'action_taken'    => $data['action_taken'] ?? $routing->action_taken,
            'received_at'     => $routing->received_at ?? now(),
            'action_taken_at' => $routing->action_taken_at ?? now(),
        ]);

        // Create next routing step
        $document = $routing->document()->with('documentType')->first();
        $newRouting = DocumentRouting::create([
            'document_id' => $routing->document_id,
            'sequence'    => $routing->sequence + 1,
            'sender_id'   => $user->id,
            'receiver_id' => $data['receiver_id'],
            'status'      => 'Pending',
            'remarks'     => $data['remarks'] ?? null,
            'due_at'      => now()->addHours($document->documentType->lead_time_hours),
        ]);

        // Update current holder
        $document->update(['current_holder_id' => $data['receiver_id']]);

        // Notify new receiver
        $newRouting->load(['document.documentType', 'sender', 'receiver']);
        try {
            Mail::to($newRouting->receiver->email)->send(new DocumentRoutedMail($newRouting, 'forwarded'));
        } catch (\Throwable $e) {
            logger()->warning('DocumentTracking forward mail failed: ' . $e->getMessage());
        }

        return back()->with('success', 'Document forwarded successfully.');
    }

    public function complete(Request $request, DocumentRouting $routing)
    {
        $user = Auth::user();
        $canComplete = $user->id === $routing->receiver_id || $user->hasAnyRole(['Administrator', 'Records']);
        abort_if(!$canComplete, 403);

        $data = $request->validate([
            'action_taken' => 'required|string|max:2000',
        ]);

        $routing->update([
            'status'          => 'Action Taken',
            'action_taken'    => $data['action_taken'],
            'action_taken_at' => now(),
            'received_at'     => $routing->received_at ?? now(),
        ]);

        $routing->document()->update([
            'overall_status'   => 'Completed',
            'current_holder_id'=> null,
        ]);

        return back()->with('success', 'Document marked as completed and filed.');
    }

    public function download(DocumentAttachment $attachment)
    {
        $user = Auth::user();
        $isAdmin = $user->hasAnyRole(['Administrator', 'Records']);

        if (!$isAdmin) {
            $doc = $attachment->document;
            $userId = $user->id;
            $hasAccess = $doc->created_by === $userId
                || $doc->routings()->where('sender_id', $userId)->orWhere('receiver_id', $userId)->exists();
            abort_if(!$hasAccess, 403);
        }

        return Storage::disk('public')->download($attachment->file_path, $attachment->file_name);
    }
}
