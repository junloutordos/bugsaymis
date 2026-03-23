<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\EmployeeDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class EmployeeDocumentController extends Controller
{
    private array $categories = [
        ['value' => 'appointment',    'label' => 'Appointment'],
        ['value' => 'pds',            'label' => 'PDS/Personal Data Sheet'],
        ['value' => 'service_record', 'label' => 'Service Record'],
        ['value' => 'performance',    'label' => 'Performance Ratings'],
        ['value' => 'eligibility',    'label' => 'Eligibility/Civil Service'],
        ['value' => 'training',       'label' => 'Training Certificates'],
        ['value' => 'medical',        'label' => 'Medical Records'],
        ['value' => 'leave',          'label' => 'Leave Records'],
        ['value' => 'other',          'label' => 'Other Documents'],
    ];

    public function index(User $user)
    {
        $this->authorize('hr.employee.view');

        $documents = EmployeeDocument::where('user_id', $user->id)
            ->with('uploadedBy:id,name')
            ->orderByDesc('document_date')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('HR/Documents/Index', [
            'employee'   => $user->load('employeeProfile'),
            'documents'  => $documents,
            'categories' => $this->categories,
            'canManage'  => Auth::user()->hasPermission('hr.employee.manage'),
        ]);
    }

    public function store(Request $request, User $user)
    {
        $this->authorize('hr.employee.manage');

        $data = $request->validate([
            'title'         => 'required|string|max:255',
            'category'      => 'required|in:appointment,pds,service_record,performance,eligibility,training,medical,leave,other',
            'document_date' => 'nullable|date',
            'description'   => 'nullable|string|max:1000',
            'file'          => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
        ]);

        $filePath        = null;
        $originalFilename = null;
        $mimeType        = null;
        $fileSize        = null;

        if ($request->hasFile('file')) {
            $file             = $request->file('file');
            $originalFilename = $file->getClientOriginalName();
            $mimeType         = $file->getMimeType();
            $fileSize         = $file->getSize();
            $filePath         = $file->store('hr/201/' . $user->id, 'local');
        }

        EmployeeDocument::create([
            'user_id'           => $user->id,
            'uploaded_by'       => Auth::id(),
            'category'          => $data['category'],
            'title'             => $data['title'],
            'description'       => $data['description'] ?? null,
            'document_date'     => $data['document_date'] ?? null,
            'file_path'         => $filePath,
            'original_filename' => $originalFilename,
            'mime_type'         => $mimeType,
            'file_size'         => $fileSize,
        ]);

        return back()->with('success', 'Document uploaded successfully.');
    }

    public function destroy(EmployeeDocument $employeeDocument)
    {
        $this->authorize('hr.employee.manage');

        if ($employeeDocument->file_path && Storage::disk('local')->exists($employeeDocument->file_path)) {
            Storage::disk('local')->delete($employeeDocument->file_path);
        }

        $employeeDocument->delete();

        return back()->with('success', 'Document deleted successfully.');
    }

    public function download(EmployeeDocument $employeeDocument)
    {
        $this->authorize('hr.employee.view');

        // Also allow the employee themselves to download their own documents
        if (
            ! Auth::user()->hasPermission('hr.employee.view') &&
            Auth::id() !== $employeeDocument->user_id
        ) {
            abort(403);
        }

        if (! $employeeDocument->file_path || ! Storage::disk('local')->exists($employeeDocument->file_path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('local')->download(
            $employeeDocument->file_path,
            $employeeDocument->original_filename ?? basename($employeeDocument->file_path)
        );
    }
}
