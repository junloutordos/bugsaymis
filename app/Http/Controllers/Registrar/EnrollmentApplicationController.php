<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Mail\EnrollmentApplicationDecisionMail;
use App\Mail\EnrollmentApplicationReceivedMail;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\EnrollmentApplication;
use App\Models\Registrar\StudentEnrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public function approve(Request $request, EnrollmentApplication $enrollmentApplication): RedirectResponse
    {
        $this->authorize('students.enrollment.manage');

        abort_if($enrollmentApplication->status === 'approved', 422, 'Already approved.');

        $data = $request->validate([
            'pisays_id' => 'required|string|max:20',
        ]);

        // Guard: PISAY ID must be unique in the students table
        $exists = DB::table('students')->where('pisaysystemID', $data['pisays_id'])->exists();
        abort_if($exists, 422, 'PISAY ID ' . $data['pisays_id'] . ' is already assigned to another student.');

        $currentSyId = SchoolYear::where('is_current', true)->value('id');

        DB::transaction(function () use ($enrollmentApplication, $data, $currentSyId) {
            // Determine batch year: grade 7 in SY 2026-2027 → graduates 2032
            // Formula: batch = school_year_start + (12 - grade_level) + 1
            // For grade 7, SY 2026-2027 start=2026: batch = 2026 + 5 + 1 = 2032
            $syStart = (int) substr(
                SchoolYear::find($currentSyId)?->name ?? '2026-2027',
                0, 4
            );
            $batch = $syStart + (12 - $enrollmentApplication->grade_level) + 1;

            // Insert into legacy students table (bypasses guarded model)
            $studentId = DB::table('students')->insertGetId([
                'lastname'        => $enrollmentApplication->lastname,
                'firstname'       => $enrollmentApplication->firstname,
                'middlename'      => $enrollmentApplication->middlename ?? '',
                'birthday'        => $enrollmentApplication->birthday ?? '',
                'sex'             => $enrollmentApplication->sex ?? '',
                'birthplace'      => $enrollmentApplication->birth_place ?? '',
                'lrn'             => $enrollmentApplication->lrn ?? '',
                'student_email'   => $enrollmentApplication->email ?? '',
                'studentcontact'  => $enrollmentApplication->contact_no ?? '',
                'province'        => $enrollmentApplication->address_province ?? '',
                'municipal'       => $enrollmentApplication->address_city ?? '',
                'barangay'        => $enrollmentApplication->address_barangay ?? '',
                'region'          => $enrollmentApplication->address_region ?? '',
                'zipcode'         => $enrollmentApplication->address_zip ?? '',
                'houseno'         => implode(', ', array_filter([
                                         $enrollmentApplication->address_house ?? '',
                                         $enrollmentApplication->address_street ?? '',
                                         $enrollmentApplication->address_subdivision ?? '',
                                     ])),
                'father_name'     => $enrollmentApplication->father_name ?? '',
                'mother_name'     => $enrollmentApplication->mother_name ?? '',
                'foccupation'     => $enrollmentApplication->father_occupation ?? '',
                'moccupation'     => $enrollmentApplication->mother_occupation ?? '',
                'contactno1'      => $enrollmentApplication->father_contact
                                     ?? $enrollmentApplication->guardian_contact ?? '',
                'contactperson'   => $enrollmentApplication->guardian_name ?? '',
                'elemschool'      => $enrollmentApplication->previous_school ?? '',
                'pisaysystemID'   => $data['pisays_id'],
                'batch'           => (string) $batch,
                'status'          => 'Enrolled',
                'date_encoded'    => now()->format('Y-m-d'),
                'ethnic'          => '',
                'regiongraduated' => '',
                'malumnus'        => '',
                'falumnus'        => '',
                'schocat'         => '',
                'dormer1'         => '',
                'pkey'            => '',
                'mbirthday'       => '',
                'fbirthday'       => '',
                'meduc'           => '',
                'feduc'           => '',
                'mschool'         => '',
                'fschool'         => '',
                'parentsstatus'   => '',
                'zipcode'         => '',
                'schooladdress'   => '',
                'relation1'       => '',
                'contact_address1'      => '',
                'contact_ofc_address1'  => '',
                'contact_ofc_telno1'    => '',
                'contactperson2'        => '',
                'relation2'             => '',
                'contact_address2'      => '',
                'contactno2'            => '',
                'contact_ofc_address2'  => '',
                'contact_ofc_telno2'    => '',
                'socioeconomic'   => '',
                'schoolType1'     => '',
                'schoolType2'     => '',
            ]);

            // Create enrollment stub (section_id = null → assigned via Section Assignment wizard)
            StudentEnrollment::create([
                'student_id'      => $studentId,
                'school_year_id'  => $currentSyId,
                'section_id'      => null,
                'grade_level'     => $enrollmentApplication->grade_level,
                'enrollment_type' => 'new',
                'status'          => 'enrolled',
                'enrollment_date' => now()->toDateString(),
                'encoded_by'      => Auth::id(),
            ]);

            // Update application
            $enrollmentApplication->update([
                'status'            => 'approved',
                'pisays_id_assigned'=> $data['pisays_id'],
                'student_id'        => $studentId,
                'reviewed_by'       => Auth::id(),
                'reviewed_at'       => now(),
            ]);
        });

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
            }
        }

        return back()->with('success', "Application {$enrollmentApplication->reference_no} approved.");
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
