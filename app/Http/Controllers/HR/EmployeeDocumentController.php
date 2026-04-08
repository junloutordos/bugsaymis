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
    // ─── CSC PRIME-HRM 201 File Folder Structure ─────────────────────────────
    // Based on CSC Memorandum Circular No. 3, s. 2001 and PRIME-HRM guidelines.

    private array $categories = [
        [
            'value'       => 'pre_employment',
            'label'       => 'Pre-Employment Requirements',
            'folder'      => 'I',
            'required'    => true,
            'description' => 'Medical certificate, NBI/police clearance, birth/marriage certificate (PSA), TOR/diploma',
        ],
        [
            'value'       => 'appointment',
            'label'       => 'Appointment Papers',
            'folder'      => 'II',
            'required'    => true,
            'description' => 'Original appointment, promotions, transfers, renewals, oath of office, assumption of duties',
        ],
        [
            'value'       => 'eligibility',
            'label'       => 'Civil Service Eligibility',
            'folder'      => 'III',
            'required'    => true,
            'description' => 'CSC eligibility certificate, PRC license, board/bar exam results',
        ],
        [
            'value'       => 'pds',
            'label'       => 'Personal Data Sheet',
            'folder'      => 'IV',
            'required'    => true,
            'description' => 'CSC Form 212 (latest and updates/amendments)',
        ],
        [
            'value'       => 'service_record',
            'label'       => 'Service Record',
            'folder'      => 'V',
            'required'    => true,
            'description' => 'Official service record, prior agency service records, money/property clearances',
        ],
        [
            'value'       => 'performance',
            'label'       => 'Performance Evaluations',
            'folder'      => 'VI',
            'required'    => false,
            'description' => 'IPCR/OPCR ratings, performance improvement plans',
        ],
        [
            'value'       => 'training',
            'label'       => 'Training & Development',
            'folder'      => 'VII',
            'required'    => false,
            'description' => 'Training certificates (local & abroad), L&D records, scholarship documents',
        ],
        [
            'value'       => 'leave_record',
            'label'       => 'Leave Records',
            'folder'      => 'VIII',
            'required'    => false,
            'description' => 'Approved leave applications, leave ledger, vacation/sick leave credits',
        ],
        [
            'value'       => 'rewards',
            'label'       => 'Rewards & Recognition',
            'folder'      => 'IX',
            'required'    => false,
            'description' => 'Awards, citations, commendations, PRAISE recognitions',
        ],
        [
            'value'       => 'disciplinary',
            'label'       => 'Disciplinary Actions',
            'folder'      => 'X',
            'required'    => false,
            'description' => 'Administrative charges, decisions, sanctions/penalty records',
        ],
        [
            'value'       => 'saln',
            'label'       => 'SALN',
            'folder'      => 'XI',
            'required'    => true,
            'description' => 'Statement of Assets, Liabilities and Net Worth (initial and annual updates)',
        ],
        [
            'value'       => 'clearance',
            'label'       => 'Clearances',
            'folder'      => 'XII',
            'required'    => false,
            'description' => 'Money & property clearances, NBI/police renewal clearances',
        ],
        [
            'value'       => 'other',
            'label'       => 'Other Documents',
            'folder'      => 'XIII',
            'required'    => false,
            'description' => 'Any other HR-related documents not covered by the above folders',
        ],
    ];

    // ─── List all employees with 201 file completeness ────────────────────────

    public function listEmployees()
    {
        $this->authorize('hr.employee.view');

        $requiredCategories = collect($this->categories)
            ->where('required', true)
            ->pluck('value')
            ->toArray();

        $employees = User::with(['employeeProfile', 'roles'])
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['Student', 'Parent']))
            ->where('status', '!=', 'inactive')
            ->orderBy('name')
            ->get()
            ->map(function (User $u) use ($requiredCategories) {
                $counts = EmployeeDocument::where('user_id', $u->id)
                    ->selectRaw('category, count(*) as cnt')
                    ->groupBy('category')
                    ->pluck('cnt', 'category')
                    ->toArray();

                $completedRequired = count(array_filter($requiredCategories, fn ($c) => ! empty($counts[$c])));
                $total             = count($requiredCategories);

                return [
                    'id'                 => $u->id,
                    'name'               => $u->name,
                    'position'           => $u->position,
                    'division'           => $u->employeeProfile?->division,
                    'employee_no'        => $u->employeeProfile?->employee_no,
                    'counts'             => $counts,
                    'total_docs'         => array_sum($counts),
                    'completeness'       => $total > 0 ? round($completedRequired / $total * 100) : 0,
                    'completed_required' => $completedRequired,
                    'total_required'     => $total,
                ];
            });

        return Inertia::render('HR/TwoOOne/Index', [
            'employees'  => $employees,
            'categories' => $this->categories,
            'canManage'  => Auth::user()->hasPermission('hr.employee.manage'),
        ]);
    }

    // ─── Per-employee 201 file detail ─────────────────────────────────────────

    public function index(User $user)
    {
        // HR/Admin can view anyone; employees can view their own
        if (
            ! Auth::user()->hasPermission('hr.employee.view') &&
            Auth::id() !== $user->id
        ) {
            abort(403);
        }

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

    // ─── Upload a document ────────────────────────────────────────────────────

    public function store(Request $request, User $user)
    {
        $this->authorize('hr.employee.manage');

        $validCategories = collect($this->categories)->pluck('value')->implode(',');

        $data = $request->validate([
            'title'         => 'required|string|max:255',
            'category'      => 'required|in:' . $validCategories,
            'document_date' => 'nullable|date',
            'description'   => 'nullable|string|max:1000',
            'file'          => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
        ]);

        $filePath         = null;
        $originalFilename = null;
        $mimeType         = null;
        $fileSize         = null;

        if ($request->hasFile('file')) {
            $file             = $request->file('file');
            // Sanitize filename: strip path separators and null bytes
            $originalFilename = basename(str_replace(["\0", '..'], '', $file->getClientOriginalName()));
            $mimeType         = $file->getMimeType(); // server-detected, not client-reported
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

    // ─── Delete ───────────────────────────────────────────────────────────────

    public function destroy(EmployeeDocument $employeeDocument)
    {
        $this->authorize('hr.employee.manage');

        if ($employeeDocument->file_path && Storage::disk('local')->exists($employeeDocument->file_path)) {
            Storage::disk('local')->delete($employeeDocument->file_path);
        }

        $employeeDocument->delete();

        return back()->with('success', 'Document deleted successfully.');
    }

    // ─── Download ─────────────────────────────────────────────────────────────

    public function download(EmployeeDocument $employeeDocument)
    {
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
