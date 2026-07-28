<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Mail\EnrollmentApplicationDecisionMail;
use App\Mail\EnrollmentApplicationReceivedMail;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\EnrollmentApplication;
use App\Services\Atlas\WorkspaceProvisioningService;
use App\Services\Registrar\StudentEnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class EnrollmentApplicationController extends Controller
{
    // ── Public: show enrollment form ──────────────────────────────────────────

    public function create(): Response
    {
        $currentSY = SchoolYear::where('is_current', true)->first(['id', 'name']);

        return Inertia::render('EnrollmentApplication/Create', [
            'schoolYear' => $currentSY,
        ]);
    }

    // ── Public: submit enrollment application ─────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $emailOrNa = fn ($attribute, $value, $fail) => (
            strtolower(trim($value)) !== 'n/a' && ! filter_var($value, FILTER_VALIDATE_EMAIL)
                ? $fail("The {$attribute} must be a valid email address or N/A.")
                : null
        );

        $data = $request->validate([
            'grade_level'             => 'required|integer|between:7,12',
            'lastname'                => 'required|string|max:100',
            'firstname'               => 'required|string|max:100',
            'middlename'              => 'required|string|max:100',
            'birthday'                => 'required|date|before:today',
            'sex'                     => 'required|in:Male,Female',
            'birth_place'             => 'required|string|max:200',
            'lrn'                     => 'required|string|max:30',
            'address'             => 'nullable|string|max:600',
            'address_house'       => 'nullable|string|max:200',
            'address_street'      => 'nullable|string|max:200',
            'address_subdivision' => 'nullable|string|max:200',
            'address_barangay'    => 'required|string|max:100',
            'address_city'        => 'required|string|max:100',
            'address_province'    => 'nullable|string|max:100',
            'address_region'      => 'required|string|max:100',
            'address_zip'         => 'nullable|string|max:10',
            'contact_no'              => 'required|string|max:20',
            'email'                   => ['required', 'string', 'max:150', $emailOrNa],
            'father_name'             => 'required|string|max:200',
            'father_occupation'       => 'required|string|max:150',
            'father_contact'          => 'required|string|max:20',
            'father_email'            => ['required', 'string', 'max:150', $emailOrNa],
            'mother_name'             => 'required|string|max:200',
            'mother_occupation'       => 'required|string|max:150',
            'mother_contact'          => 'required|string|max:20',
            'mother_email'            => ['required', 'string', 'max:150', $emailOrNa],
            'guardian_name'           => 'required|string|max:200',
            'guardian_relationship'   => 'required|string|max:100',
            'guardian_contact'        => 'required|string|max:20',
            'guardian_email'          => ['required', 'string', 'max:150', $emailOrNa],
            'previous_school'         => 'required|string|max:300',
            'previous_school_address' => 'required|string|max:300',
            'grade_level_completed'   => 'required|string|max:50',
            'school_year_completed'   => 'required|string|max:30',
        ]);

        // Compose full address string server-side if not sent from frontend
        if (empty($data['address'])) {
            $data['address'] = implode(', ', array_filter([
                $data['address_house'] ?? '',
                $data['address_street'] ?? '',
                $data['address_subdivision'] ?? '',
                !empty($data['address_barangay']) ? 'Brgy. ' . $data['address_barangay'] : '',
                $data['address_city'] ?? '',
                $data['address_province'] ?? '',
            ]));
        }

        $currentSyId = SchoolYear::where('is_current', true)->value('id');
        abort_unless($currentSyId, 422, 'No active school year configured.');

        $application = EnrollmentApplication::create([
            ...$data,
            'reference_no'   => EnrollmentApplication::generateReferenceNo(),
            'school_year_id' => $currentSyId,
            'status'         => 'pending',
        ]);

        if ($application->email) {
            try {
                Mail::to($application->email)
                    ->send(new EnrollmentApplicationReceivedMail($application));
            } catch (\Throwable $e) {
                logger()->warning('Enrollment confirmation email failed', [
                    'ref'   => $application->reference_no,
                    'error' => $e->getMessage(),
                ]);
                report($e);
            }
        }

        return redirect()->route('enrollment-application.success', $application->reference_no);
    }

    // ── Public: confirmation page ─────────────────────────────────────────────

    public function success(string $referenceNo): Response
    {
        $application = EnrollmentApplication::where('reference_no', $referenceNo)
            ->firstOrFail(['reference_no', 'firstname', 'lastname', 'grade_level', 'email', 'status']);

        return Inertia::render('EnrollmentApplication/Success', [
            'application' => $application,
        ]);
    }

    // ── Registrar: list applications ──────────────────────────────────────────

    public function index(Request $request): Response
    {
        $this->authorize('students.enrollment.manage');

        $status = $request->input('status', 'pending');
        $search = trim($request->input('search', ''));

        $query = EnrollmentApplication::with('schoolYear:id,name')
            ->where('status', $status)
            ->when($search, fn ($q) => $q->where(function ($q2) use ($search) {
                $q2->where('lastname', 'like', "%{$search}%")
                   ->orWhere('firstname', 'like', "%{$search}%")
                   ->orWhere('reference_no', 'like', "%{$search}%")
                   ->orWhere('lrn', 'like', "%{$search}%");
            }))
            ->orderBy('created_at')
            ->paginate(30)
            ->withQueryString();

        $counts = EnrollmentApplication::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return Inertia::render('Registrar/EnrollmentApplications/Index', [
            'applications'  => $query,
            'counts'        => $counts,
            'filters'       => compact('status', 'search'),
        ]);
    }

    // ── Registrar: view single application ────────────────────────────────────

    public function show(EnrollmentApplication $enrollmentApplication): Response
    {
        $this->authorize('students.enrollment.manage');

        return Inertia::render('Registrar/EnrollmentApplications/Show', [
            'application' => $enrollmentApplication->load('schoolYear:id,name', 'reviewer:id,name'),
        ]);
    }

    // ── Registrar: approve ────────────────────────────────────────────────────

    public function approve(Request $request, EnrollmentApplication $enrollmentApplication, WorkspaceProvisioningService $provisioning, StudentEnrollmentService $enrollmentService): RedirectResponse
    {
        $this->authorize('students.enrollment.manage');

        abort_if($enrollmentApplication->status === 'approved', 422, 'Already approved.');

        $data = $request->validate([
            'pisays_id' => 'required|string|max:20',
        ]);

        $currentSyId = SchoolYear::where('is_current', true)->value('id');

        $result = $enrollmentService->createStudentAndEnroll(
            $enrollmentApplication->only([
                'lastname', 'firstname', 'middlename', 'birthday', 'sex', 'birth_place', 'lrn',
                'address_house', 'address_street', 'address_subdivision', 'address_barangay',
                'address_city', 'address_province', 'address_region', 'address_zip',
                'contact_no', 'email',
                'father_name', 'father_occupation', 'father_contact',
                'mother_name', 'mother_occupation', 'mother_contact',
                'guardian_name', 'guardian_contact',
                'previous_school',
            ]),
            $data['pisays_id'],
            $enrollmentApplication->grade_level,
            $currentSyId,
            'new',
            Auth::id(),
        );

        $newStudentId = $result['student_id'];

        $enrollmentApplication->update([
            'status'            => 'approved',
            'pisays_id_assigned'=> $data['pisays_id'],
            'student_id'        => $newStudentId,
            'reviewed_by'       => Auth::id(),
            'reviewed_at'       => now(),
        ]);

        // Provision the official Google Workspace account outside the DB
        // transaction (external API call). Never blocks enrollment — if this
        // fails, the student is simply picked up later by the Workspace Sync
        // review queue instead.
        $provisioned = $provisioning->provisionStudent($newStudentId);

        // Send decision email
        if ($enrollmentApplication->email) {
            try {
                Mail::to($enrollmentApplication->email)
                    ->send(new EnrollmentApplicationDecisionMail($enrollmentApplication->fresh()));
            } catch (\Throwable $e) {
                logger()->warning('Enrollment decision email failed', [
                    'ref'   => $enrollmentApplication->reference_no,
                    'error' => $e->getMessage(),
                ]);
                report($e);
            }
        }

        $message = "Application {$enrollmentApplication->reference_no} approved.";

        if ($provisioned) {
            $message .= " Google account created: {$provisioned['email']} — temporary password: {$provisioned['temp_password']} (shown once, share with the student directly, not by email).";
        }

        return back()->with('success', $message);
    }

    // ── Registrar: reject ─────────────────────────────────────────────────────

    public function reject(Request $request, EnrollmentApplication $enrollmentApplication): RedirectResponse
    {
        $this->authorize('students.enrollment.manage');

        abort_if($enrollmentApplication->status === 'approved', 422, 'Cannot reject an already-approved application.');

        $data = $request->validate([
            'remarks' => 'required|string|max:1000',
        ]);

        $enrollmentApplication->update([
            'status'      => 'rejected',
            'remarks'     => $data['remarks'],
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        if ($enrollmentApplication->email) {
            try {
                Mail::to($enrollmentApplication->email)
                    ->send(new EnrollmentApplicationDecisionMail($enrollmentApplication));
            } catch (\Throwable $e) {
                logger()->warning('Enrollment rejection email failed', [
                    'ref'   => $enrollmentApplication->reference_no,
                    'error' => $e->getMessage(),
                ]);
                report($e);
            }
        }

        return back()->with('success', "Application {$enrollmentApplication->reference_no} rejected.");
    }

    // ── Registrar: reopen rejected application ────────────────────────────────

    public function reopen(EnrollmentApplication $enrollmentApplication): RedirectResponse
    {
        $this->authorize('students.enrollment.manage');

        abort_unless($enrollmentApplication->status === 'rejected', 422, 'Only rejected applications can be reopened.');

        $enrollmentApplication->update([
            'status'      => 'pending',
            'remarks'     => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        return back()->with('success', 'Application reopened for review.');
    }
}
