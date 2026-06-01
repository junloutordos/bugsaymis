<?php

namespace App\Http\Controllers;

use App\Mail\DocumentCompletedMail;
use App\Mail\DocumentCreatedMail;
use App\Mail\DocumentReceivedMail;
use App\Mail\DocumentReturnedMail;
use App\Mail\DocumentRoutedMail;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentRouting;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\GoogleDriveService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class DocumentTrackingController extends Controller
{
    public function __construct(private GoogleDriveService $drive) {}

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formatDoc(Document $doc): array
    {
        $doc->loadMissing([
            'documentType:id,name,code,lead_time_hours,routing_type',
            'creator:id,name',
            'currentHolder:id,name',
            'attachments',
            'routings' => fn($q) => $q->orderBy('sequence'),
            'routings.sender:id,name',
            'routings.receiver:id,name',
        ]);

        return [
            'id'               => $doc->id,
            'tracking_no'      => $doc->tracking_no,
            'subject'          => $doc->subject,
            'description'      => $doc->description,
            'origin_type'      => $doc->origin_type,
            'source_office'    => $doc->source_office,
            'sender_name'      => $doc->sender_name,
            'date_of_document' => $doc->date_of_document?->toDateString(),
            'date_received'    => $doc->date_received?->toDateString(),
            'document_number'  => $doc->document_number,
            'priority'         => $doc->priority,
            'urgency'          => $doc->urgency,
            'is_confidential'  => $doc->is_confidential,
            'overall_status'   => $doc->overall_status,
            'deadline_at'      => $doc->deadline_at?->toISOString(),
            'completed_at'     => $doc->completed_at?->toISOString(),
            'created_at'       => $doc->created_at?->toISOString(),
            'document_type'    => $doc->documentType?->only('id', 'name', 'code', 'lead_time_hours', 'routing_type'),
            'creator'          => $doc->creator?->only('id', 'name'),
            'current_holder'   => $doc->currentHolder?->only('id', 'name'),
            'attachments'      => $doc->attachments->map(fn($a) => [
                'id'            => $a->id,
                'file_name'     => $a->file_name,
                'mime_type'     => $a->mime_type,
                'file_size'     => $a->file_size,
                'gdrive_file_id'=> $a->gdrive_file_id,
                'gdrive_link'   => $a->gdrive_link,
                'has_preview'   => (bool) $a->gdrive_file_id,
            ]),
            'routings'         => $doc->routings->map(fn($r) => [
                'id'              => $r->id,
                'sequence'        => $r->sequence,
                'sender'          => $r->sender?->only('id', 'name'),
                'receiver'        => $r->receiver?->only('id', 'name'),
                'status'          => $r->status,
                'instructions'    => $r->instructions,
                'action_taken'    => $r->action_taken,
                'remarks'         => $r->remarks,
                'return_reason'   => $r->return_reason,
                'is_terminal'     => $r->is_terminal,
                'received_at'     => $r->received_at?->toISOString(),
                'action_taken_at' => $r->action_taken_at?->toISOString(),
                'forwarded_at'    => $r->forwarded_at?->toISOString(),
                'returned_at'     => $r->returned_at?->toISOString(),
                'due_at'          => $r->due_at?->toISOString(),
                'is_overdue'      => $r->is_overdue,
                'created_at'      => $r->created_at?->toISOString(),
            ]),
        ];
    }

    private function resolveStepReceivers(\App\Models\DocumentTypeRoutingStep $step): \Illuminate\Support\Collection
    {
        // Preferred: specific assigned user
        if ($step->assigned_user_id) {
            $u = User::find($step->assigned_user_id);
            return $u ? collect([$u]) : collect();
        }
        // Fallback: all users of a role (legacy)
        if ($step->role_name) {
            return User::havingRole($step->role_name)->get();
        }
        return collect();
    }

    private function sendMail(callable $fn): void
    {
        try { $fn(); } catch (\Throwable $e) {
            logger()->warning('DTS mail failed: ' . $e->getMessage());
        }
    }

    private function notify(User $user, string $subject, string $refNo, string $status, string $url): void
    {
        try { NotificationService::notifyUser($user, $subject, $refNo, $status, $url); } catch (\Throwable) {}
    }

    private function dtsFolder(): string
    {
        return config('services.google_drive.dts_folder_id');
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index()
    {
        $user    = Auth::user();
        $isAdmin = $user->hasPermission('documents.approve');

        $query = Document::with([
            'documentType:id,name,code',
            'creator:id,name',
            'currentHolder:id,name',
            'routings' => fn($q) => $q->orderBy('sequence'),
            'routings.sender:id,name',
            'routings.receiver:id,name',
        ])->latest();

        if (! $isAdmin) {
            $uid = $user->id;
            $query->where(function ($q) use ($uid) {
                $q->where('created_by', $uid)
                  ->orWhereHas('routings', fn($r) =>
                      $r->where('sender_id', $uid)->orWhere('receiver_id', $uid)
                  );
            });
        }

        $documents = $query->get()->map(fn($d) => $this->formatDoc($d));

        return Inertia::render('DocumentTracking/Index', [
            'documents'     => $documents,
            'documentTypes' => DocumentType::where('is_active', true)->orderBy('name')
                                ->with(['routingSteps.office', 'routingSteps.assignedUser'])
                                ->get(['id', 'name', 'code', 'routing_type', 'lead_time_hours']),
            'users'         => User::where('status', '<>', 'inactive')->orderBy('name')->get(['id', 'name', 'office_id']),
            'offices'       => \App\Models\Office::orderBy('name')->get(['id', 'name']),
            'canLogExternal'=> $user->hasPermission('documents.approve'),
            'isAdmin'       => $isAdmin,
        ]);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $isExternal = $request->input('origin_type') === 'external';

        $data = $request->validate([
            'origin_type'      => 'required|in:internal,external',
            'document_type_id' => 'required|exists:document_types,id',
            'subject'          => 'required|string|max:500',
            'description'      => 'nullable|string|max:5000',
            'priority'         => 'required|in:Normal,Urgent,Rush',
            'urgency'          => 'required|in:Normal,Urgent,Very Urgent',
            'is_confidential'  => 'nullable|boolean',
            'deadline_at'      => 'nullable|date',
            // External-only
            'source_office'    => $isExternal ? 'required|string|max:255' : 'nullable|string|max:255',
            'sender_name'      => 'nullable|string|max:255',
            'date_of_document' => 'nullable|date',
            'date_received'    => 'nullable|date',
            'document_number'  => 'nullable|string|max:100',
            // Scan upload (base64, Cloudflare-safe)
            'scan_base64'      => 'nullable|string',
            'scan_filename'    => 'nullable|string|max:255',
            'scan_mime'        => 'nullable|string|max:100',
            // Manual first receiver override (used when routing_type = manual)
            'receiver_id'      => 'nullable|exists:users,id',
            'instructions'     => 'nullable|string|max:2000',
        ]);

        $docType = DocumentType::with('routingSteps')->findOrFail($data['document_type_id']);
        $user    = Auth::user();

        $document = DB::transaction(function () use ($data, $docType, $user, $isExternal) {
            $doc = Document::create([
                'tracking_no'      => 'TEMP',
                'document_type_id' => $data['document_type_id'],
                'subject'          => $data['subject'],
                'description'      => $data['description'] ?? null,
                'priority'         => $data['priority'],
                'urgency'          => $data['urgency'],
                'is_confidential'  => $data['is_confidential'] ?? false,
                'origin_type'      => $data['origin_type'],
                'source_office'    => $data['source_office'] ?? null,
                'sender_name'      => $data['sender_name'] ?? null,
                'date_of_document' => $data['date_of_document'] ?? null,
                'date_received'    => $data['date_received'] ?? now()->toDateString(),
                'document_number'  => $data['document_number'] ?? null,
                'deadline_at'      => $data['deadline_at'] ?? null,
                'created_by'       => $user->id,
                'overall_status'   => 'In-Transit',
            ]);

            // ── External documents: Records holds it first, then forwards to OCD ──
            // Records Officer is always the initial holder. They use the Forward
            // button to send to OCD. No auto-routing from the type template.
            if ($isExternal) {
                DocumentRouting::create([
                    'document_id'  => $doc->id,
                    'sequence'     => 1,
                    'sender_id'    => $user->id,
                    'receiver_id'  => $user->id,
                    'status'       => 'Received',
                    'instructions' => 'Document received and logged by Records Office. Forward to OCD for review.',
                    'received_at'  => now(),
                    'is_terminal'  => false,
                ]);
                $doc->update(['current_holder_id' => $user->id]);
                return $doc;
            }

            // ── Internal documents: route from type template ───────────────────
            $steps = $docType->routingSteps;
            if ($docType->routing_type === 'manual' || $steps->isEmpty()) {
                $receiverId = $data['receiver_id'] ?? null;
                if ($receiverId) {
                    DocumentRouting::create([
                        'document_id'  => $doc->id,
                        'sequence'     => 1,
                        'sender_id'    => $user->id,
                        'receiver_id'  => $receiverId,
                        'status'       => 'Pending',
                        'instructions' => $data['instructions'] ?? null,
                        'due_at'       => now()->addHours($docType->lead_time_hours),
                        'is_terminal'  => false,
                    ]);
                    $doc->update(['current_holder_id' => $receiverId]);
                }
            } elseif ($docType->routing_type === 'parallel') {
                foreach ($steps as $step) {
                    $receivers = $this->resolveStepReceivers($step);
                    foreach ($receivers as $recv) {
                        DocumentRouting::create([
                            'document_id'  => $doc->id,
                            'sequence'     => 1,
                            'sender_id'    => $user->id,
                            'receiver_id'  => $recv->id,
                            'status'       => 'Pending',
                            'instructions' => $step->action_required,
                            'due_at'       => now()->addHours($step->lead_time_hours),
                            'is_terminal'  => false,
                        ]);
                    }
                }
            } else {
                // Sequential: activate first step, queue the rest
                foreach ($steps as $i => $step) {
                    $isFirst   = $i === 0;
                    $receivers = $this->resolveStepReceivers($step);
                    foreach ($receivers as $recv) {
                        DocumentRouting::create([
                            'document_id'  => $doc->id,
                            'sequence'     => $step->step_order,
                            'sender_id'    => $user->id,
                            'receiver_id'  => $recv->id,
                            'status'       => $isFirst ? 'Pending' : 'Queued',
                            'instructions' => $step->action_required,
                            'due_at'       => $isFirst ? now()->addHours($step->lead_time_hours) : null,
                            'is_terminal'  => ($i === $steps->count() - 1),
                        ]);
                        if ($isFirst) $doc->update(['current_holder_id' => $recv->id]);
                    }
                }
            }

            return $doc;
        });

        // Upload scan to Google Drive
        if (! empty($data['scan_base64'])) {
            try {
                $raw      = base64_decode(preg_replace('/^data:[^;]+;base64,/', '', $data['scan_base64']));
                $fileName = ($data['scan_filename'] ?? $document->tracking_no) . '_scan';
                $mime     = $data['scan_mime'] ?? 'application/pdf';
                $result   = $this->drive->uploadRaw($raw, $fileName, $mime, $this->dtsFolder());

                DocumentAttachment::create([
                    'document_id'   => $document->id,
                    'file_name'     => $data['scan_filename'] ?? $document->tracking_no . '.pdf',
                    'mime_type'     => $mime,
                    'file_size'     => strlen($raw),
                    'gdrive_file_id'=> $result['file_id'],
                    'gdrive_link'   => $result['link'],
                    'uploaded_by'   => Auth::id(),
                ]);
            } catch (\Throwable $e) {
                logger()->error('DTS scan upload failed', ['error' => $e->getMessage()]);
            }
        }

        $url = route('document-tracking.show', $document->id);

        if ($document->origin_type === 'external') {
            // External: Records already holds it — no notification to self.
            // Nothing to send here; Records will notify OCD when they forward.
        } else {
            // Internal: notify first active (Pending) receivers
            $firstRoutings = $document->routings()->with(['sender', 'receiver', 'document.documentType'])
                ->where('status', 'Pending')->get();
            foreach ($firstRoutings as $routing) {
                $recv = $routing->receiver;
                if (! $recv) continue;
                $this->sendMail(fn() => Mail::to($recv->email)->send(new DocumentCreatedMail($document, $recv->name)));
                $this->notify($recv, 'Document Tracking', $document->tracking_no, 'New document requires your review', $url);
            }
        }

        return redirect()->route('document-tracking.show', $document->id)
            ->with('success', "Document {$document->tracking_no} logged successfully.");
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(Document $document)
    {
        $user    = Auth::user();
        $isAdmin = $user->hasPermission('documents.approve');

        if (! $isAdmin) {
            $uid       = $user->id;
            $hasAccess = $document->created_by === $uid
                || $document->routings()->where('sender_id', $uid)->orWhere('receiver_id', $uid)->exists();
            abort_if(! $hasAccess, 403);
        }

        return Inertia::render('DocumentTracking/Show', [
            'document'      => $this->formatDoc($document),
            'users'         => User::where('status', '<>', 'inactive')->orderBy('name')->get(['id', 'name', 'office_id']),
            'offices'       => \App\Models\Office::orderBy('name')->get(['id', 'name']),
            'isAdmin'       => $isAdmin,
            'currentUserId' => $user->id,
        ]);
    }

    // ── Receive ───────────────────────────────────────────────────────────────

    public function receive(DocumentRouting $routing)
    {
        abort_if(Auth::id() !== $routing->receiver_id, 403);
        abort_if($routing->status !== 'Pending', 422, 'Already acknowledged.');

        $routing->update(['status' => 'Received', 'received_at' => now()]);

        $routing->load(['document', 'sender', 'receiver']);
        if ($routing->sender) {
            $this->sendMail(fn() => Mail::to($routing->sender->email)->send(new DocumentReceivedMail($routing)));
            $this->notify($routing->sender, 'Document Tracking', $routing->document->tracking_no, "Acknowledged by {$routing->receiver->name}", route('document-tracking.show', $routing->document_id));
        }

        return back()->with('success', 'Document acknowledged.');
    }

    // ── Annotate ──────────────────────────────────────────────────────────────

    public function annotate(Request $request, Document $document)
    {
        $uid = Auth::id();
        $canAnnotate = $document->created_by === $uid
            || $document->routings()->where('receiver_id', $uid)->exists()
            || Auth::user()->hasAnyPermission(['documents.approve', 'documents.update']);
        abort_if(! $canAnnotate, 403);

        $data = $request->validate(['remarks' => 'required|string|max:2000']);

        // Update the latest active routing for this user, or the latest overall
        $routing = $document->routings()
            ->where('receiver_id', $uid)
            ->whereIn('status', ['Pending', 'Received'])
            ->latest()
            ->first()
            ?? $document->routings()->latest()->first();

        if ($routing) {
            $routing->update(['remarks' => $data['remarks']]);
        }

        return back()->with('success', 'Note added.');
    }

    // ── Act ───────────────────────────────────────────────────────────────────

    public function act(Request $request, DocumentRouting $routing)
    {
        abort_if(Auth::id() !== $routing->receiver_id, 403);
        abort_if(! in_array($routing->status, ['Pending', 'Received']), 422, 'Cannot act on this step.');

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

        // In sequential routing, activate the next Queued step
        $doc = $routing->document;
        if ($doc->documentType?->routing_type === 'sequential') {
            $nextRoutings = $doc->routings()
                ->where('sequence', $routing->sequence + 1)
                ->where('status', 'Queued')
                ->get();
            foreach ($nextRoutings as $next) {
                $next->update([
                    'status' => 'Pending',
                    'due_at' => now()->addHours($next->documentType?->lead_time_hours ?? 24),
                ]);
                $doc->update(['current_holder_id' => $next->receiver_id]);
                if ($next->receiver) {
                    $this->sendMail(fn() => Mail::to($next->receiver->email)->send(new DocumentRoutedMail($next, 'forwarded')));
                    $this->notify($next->receiver, 'Document Tracking', $doc->tracking_no, 'Document routed to you for action', route('document-tracking.show', $doc->id));
                }
            }
        }

        // Notify creator + OCD
        $routing->load(['document.creator', 'receiver']);
        $creator = $routing->document->creator;
        if ($creator && $creator->id !== $routing->receiver_id) {
            $this->sendMail(fn() => Mail::to($creator->email)->send(
                new \App\Mail\DocumentCompletedMail($routing->document, $creator->name, $routing->receiver->name ?? 'Staff')
            ));
            $this->notify($creator, 'Document Tracking', $routing->document->tracking_no, "Action recorded by {$routing->receiver->name}", route('document-tracking.show', $routing->document_id));
        }

        $ocdUsers = User::havingRole('OCD')->where('id', '<>', $routing->receiver_id)->get();
        foreach ($ocdUsers as $ocd) {
            $this->notify($ocd, 'Document Tracking', $routing->document->tracking_no, "Action recorded on document", route('document-tracking.show', $routing->document_id));
        }

        return back()->with('success', 'Action recorded.');
    }

    // ── Forward ───────────────────────────────────────────────────────────────

    public function forward(Request $request, DocumentRouting $routing)
    {
        abort_if(Auth::id() !== $routing->receiver_id, 403);
        abort_if($routing->status === 'Forwarded', 422, 'Already forwarded.');

        $data = $request->validate([
            'receiver_id'  => 'required|exists:users,id',
            'instructions' => 'nullable|string|max:2000',
            'remarks'      => 'nullable|string|max:2000',
        ]);

        $routing->update([
            'status'       => 'Forwarded',
            'forwarded_at' => now(),
            'received_at'  => $routing->received_at ?? now(),
            'remarks'      => $data['remarks'] ?? $routing->remarks,
        ]);

        $doc = $routing->document()->with('documentType')->first();

        $newRouting = DocumentRouting::create([
            'document_id'  => $routing->document_id,
            'sequence'     => $routing->sequence + 1,
            'sender_id'    => Auth::id(),
            'receiver_id'  => $data['receiver_id'],
            'status'       => 'Pending',
            'instructions' => $data['instructions'] ?? null,
            'due_at'       => now()->addHours($doc->documentType?->lead_time_hours ?? 24),
            'is_terminal'  => false,
        ]);

        $doc->update(['current_holder_id' => $data['receiver_id']]);

        $newRouting->load(['document.documentType', 'sender', 'receiver']);
        if ($newRouting->receiver) {
            $this->sendMail(fn() => Mail::to($newRouting->receiver->email)->send(new DocumentRoutedMail($newRouting, 'forwarded')));
            $this->notify($newRouting->receiver, 'Document Tracking', $doc->tracking_no, 'Document forwarded to you', route('document-tracking.show', $doc->id));
        }

        return back()->with('success', 'Document forwarded.');
    }

    // ── Return ────────────────────────────────────────────────────────────────

    public function returnDoc(Request $request, DocumentRouting $routing)
    {
        abort_if(Auth::id() !== $routing->receiver_id, 403);
        abort_if(! in_array($routing->status, ['Pending', 'Received']), 422, 'Cannot return at this stage.');

        $data = $request->validate(['return_reason' => 'required|string|max:2000']);

        $routing->update([
            'status'      => 'Returned',
            'returned_at' => now(),
            'return_reason'=> $data['return_reason'],
            'received_at' => $routing->received_at ?? now(),
        ]);

        $doc = $routing->document;
        $doc->update([
            'current_holder_id' => $routing->sender_id,
            'overall_status'    => 'Returned',
        ]);

        $routing->load(['sender', 'receiver', 'document']);
        if ($routing->sender) {
            $this->sendMail(fn() => Mail::to($routing->sender->email)->send(new DocumentReturnedMail($routing)));
            $this->notify($routing->sender, 'Document Tracking', $doc->tracking_no, "Document returned by {$routing->receiver->name}", route('document-tracking.show', $doc->id));
        }

        return back()->with('success', 'Document returned to sender.');
    }

    // ── Complete ──────────────────────────────────────────────────────────────

    public function complete(Request $request, Document $document)
    {
        $user      = Auth::user();
        $canClose  = $user->hasAnyPermission(['documents.approve', 'documents.update'])
            || $document->routings()->where('receiver_id', $user->id)->whereIn('status', ['Received', 'Action Taken'])->exists();
        abort_if(! $canClose, 403);

        $data = $request->validate(['action_taken' => 'nullable|string|max:2000']);

        $document->update([
            'overall_status' => 'Completed',
            'completed_at'   => now(),
            'current_holder_id' => null,
        ]);

        // Mark all active routings as completed
        $document->routings()->whereIn('status', ['Pending', 'Received', 'Queued'])->update([
            'status'          => 'Action Taken',
            'action_taken'    => $data['action_taken'] ?? 'Document filed and completed.',
            'action_taken_at' => now(),
            'received_at'     => now(),
        ]);

        $completedBy = $user->name;
        $url         = route('document-tracking.show', $document->id);

        $creator = $document->creator;
        if ($creator && $creator->id !== $user->id) {
            $this->sendMail(fn() => Mail::to($creator->email)->send(new DocumentCompletedMail($document, $creator->name, $completedBy)));
            $this->notify($creator, 'Document Tracking', $document->tracking_no, 'Document completed and filed', $url);
        }

        $ocdUsers = User::havingRole('OCD')->where('id', '<>', $user->id)->get();
        foreach ($ocdUsers as $ocd) {
            $this->sendMail(fn() => Mail::to($ocd->email)->send(new DocumentCompletedMail($document, $ocd->name, $completedBy)));
            $this->notify($ocd, 'Document Tracking', $document->tracking_no, 'Document completed and filed', $url);
        }

        return back()->with('success', 'Document marked as completed and filed.');
    }

    // ── View Scan (Google Drive proxy) ────────────────────────────────────────

    public function viewScan(Document $document, DocumentAttachment $attachment)
    {
        $user    = Auth::user();
        $isAdmin = $user->hasPermission('documents.approve');

        if (! $isAdmin) {
            $uid       = $user->id;
            $hasAccess = $document->created_by === $uid
                || $document->routings()->where('sender_id', $uid)->orWhere('receiver_id', $uid)->exists();
            abort_if(! $hasAccess, 403);
        }

        abort_if($attachment->document_id !== $document->id, 404);
        abort_if(! $attachment->gdrive_file_id, 404, 'No scan available.');

        try {
            $file = $this->drive->download($attachment->gdrive_file_id);
            return response($file['content'], 200, [
                'Content-Type'        => $file['mimeType'],
                'Content-Disposition' => 'inline; filename="' . $attachment->file_name . '"',
                'Cache-Control'       => 'private, max-age=300',
            ]);
        } catch (\Throwable $e) {
            logger()->error('DTS scan download failed', ['error' => $e->getMessage()]);
            abort(502, 'Could not retrieve scan from Google Drive.');
        }
    }
}
