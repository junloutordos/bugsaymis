<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\GuidanceConsultation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
// (no explicit JsonResponse type on assign to allow redirects or JSON responses)
use Carbon\Carbon;

class GuidanceConsultationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Only allow access to Guidance Services if user's numeric role_id includes 17 (Guidance) or 1 (Administrator)
        $roleIds = array_filter(array_map('intval', array_map('trim', explode(',', $user->role_id ?? ''))));
        $allowed = in_array(17, $roleIds) || in_array(1, $roleIds);
        if (! $allowed) {
            abort(403, 'Forbidden');
        }

        $query = GuidanceConsultation::query()->orderByDesc('created_at');

        $items = $query->get()->map(function ($row) {
            $r = $row->toArray();
            $r['requestor_name'] = null;
            $r['assigned_personnel_name'] = null;
            $r['referred_by_name'] = null;
            // try to resolve as student first
            $student = DB::table('students')->where('id', $row->requestor_id)->first();
            if ($student) {
                // pick common name columns
                if (isset($student->lastname) || isset($student->firstname)) {
                    $last = $student->lastname ?? ($student->last_name ?? null);
                    $first = $student->firstname ?? ($student->first_name ?? null);
                    $r['requestor_name'] = trim(($last ? $last . ', ' : '') . ($first ?? '')) ?: ($student->name ?? null);
                } else {
                    $r['requestor_name'] = $student->name ?? null;
                }
            } else {
                $userRow = DB::table('users')->where('id', $row->requestor_id)->first();
                if ($userRow) $r['requestor_name'] = $userRow->name ?? null;
            }

            // resolve assigned personnel id to name if present
            if (!empty($row->assigned_personnel)) {
                $u = DB::table('users')->where('id', $row->assigned_personnel)->first();
                if ($u) $r['assigned_personnel_name'] = $u->name ?? null;
            }

            // resolve referred_by user id to name if present
            if (!empty($row->referred_by) && is_numeric($row->referred_by)) {
                $referredBy = DB::table('users')->where('id', (int) $row->referred_by)->first();
                if ($referredBy) $r['referred_by_name'] = $referredBy->name ?? null;
            }

            return $r;
        });

        return Inertia::render('Guidance/Consultations/Index', [
            'consultations' => $items,
            // pass list of faculty users for adviser selection
            'facultyUsers' => User::havingRole('Faculty')->select('id','name','email')->get(),
        ]);
    }

    /**
     * Search students for referral modal autocomplete.
     */
    public function searchStudents(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->hasAnyRole(['Administrator', 'Faculty', 'Staff', 'Guidance'])) {
            abort(403, 'Forbidden');
        }

        $term = trim((string) $request->query('q', ''));
        if ($term === '') {
            return response()->json(['students' => []]);
        }

        $limit = (int) $request->query('limit', 10);
        $limit = max(1, min($limit, 20));

        $studentsTableColumns = collect(DB::select('SHOW COLUMNS FROM students'))
            ->map(fn($column) => $column->Field)
            ->all();

        $firstNameCandidates = ['firstname', 'first_name', 'fname'];
        $lastNameCandidates = ['lastname', 'last_name', 'lname'];
        $pisayCandidates = ['pisaysystemid','pisaysystemID','pisaysystem_id','pisay_system_id','pisay_id','pisayid'];

        $firstNameColumn = collect($firstNameCandidates)->first(fn($column) => in_array($column, $studentsTableColumns));
        $lastNameColumn = collect($lastNameCandidates)->first(fn($column) => in_array($column, $studentsTableColumns));
        $pisayColumn = collect($pisayCandidates)->first(fn($column) => in_array($column, $studentsTableColumns));

        $studentsQuery = DB::table('students');

        if ($firstNameColumn || $lastNameColumn) {
            $studentsQuery->where(function ($query) use ($term, $firstNameColumn, $lastNameColumn) {
                if ($firstNameColumn) {
                    $query->orWhere($firstNameColumn, 'like', "%{$term}%");
                }
                if ($lastNameColumn) {
                    $query->orWhere($lastNameColumn, 'like', "%{$term}%");
                }
                if ($firstNameColumn && $lastNameColumn) {
                    $query->orWhereRaw("CONCAT(`{$lastNameColumn}`, ', ', `{$firstNameColumn}`) LIKE ?", ["%{$term}%"]);
                }
            });
        } else {
            $quotedColumns = implode(', ', array_map(fn($column) => "`{$column}`", $studentsTableColumns));
            $studentsQuery->whereRaw("LOWER(CONCAT_WS(' ', {$quotedColumns})) LIKE ?", ['%' . strtolower($term) . '%']);
        }

        if ($pisayColumn) {
            $studentsQuery->orWhere($pisayColumn, 'like', "%{$term}%");
        }

        $students = $studentsQuery->orderBy('id', 'desc')->limit($limit)->get();

        $mapped = $students->map(function ($student) use ($firstNameCandidates, $lastNameCandidates, $pisayCandidates) {
            $first = null;
            $last = null;
            $pisay = null;

            foreach ($firstNameCandidates as $column) {
                if (isset($student->$column) && $student->$column !== null && $student->$column !== '') {
                    $first = $student->$column;
                    break;
                }
            }

            foreach ($lastNameCandidates as $column) {
                if (isset($student->$column) && $student->$column !== null && $student->$column !== '') {
                    $last = $student->$column;
                    break;
                }
            }

            foreach ($pisayCandidates as $column) {
                if (isset($student->$column) && $student->$column !== null && $student->$column !== '') {
                    $pisay = $student->$column;
                    break;
                }
            }

            $displayName = trim(($last ? $last . ', ' : '') . ($first ?? ''));
            if ($displayName === '') {
                $displayName = $student->name ?? ('Student #' . $student->id);
            }

            return [
                'id' => $student->id,
                'name' => $displayName,
                'pisay' => $pisay,
            ];
        })->values();

        return response()->json(['students' => $mapped]);
    }

    /**
     * Store a student referral to Guidance Office.
     */
    public function storeReferral(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->hasAnyRole(['Administrator', 'Faculty', 'Staff', 'Guidance'])) {
            abort(403, 'Forbidden');
        }

        $data = $request->validate([
            'requestor_id' => ['required', 'integer', 'exists:students,id'],
            'referral_category' => ['required', 'array', 'min:1'],
            'referral_category.*' => ['required', 'string', 'in:Academic,Behavior,Personal / Social'],
            'behavior_spotted' => ['required', 'array', 'min:1'],
            'behavior_spotted.*' => ['required', 'string', 'max:255'],
            'behavior_other' => ['nullable', 'string', 'max:255'],
            'brief_description' => ['required', 'string', 'max:4000'],
        ]);

        $othersLabel = 'Others (please specify)';
        $behaviors = collect($data['behavior_spotted'])->filter(fn($value) => $value !== null && $value !== '')->values();

        if ($behaviors->contains($othersLabel)) {
            if (empty(trim((string) ($data['behavior_other'] ?? '')))) {
                return response()->json(['message' => 'Please specify the Others behavior.'], 422);
            }

            $behaviors = $behaviors
                ->reject(fn($value) => $value === $othersLabel)
                ->push($othersLabel . ': ' . trim((string) $data['behavior_other']))
                ->values();
        }

        $consultation = GuidanceConsultation::create([
            'requestor_id' => $data['requestor_id'],
            'referred_by' => (string) $user->id,
            'consultation_type' => 'referred',
            'referral_category' => implode(', ', $data['referral_category']),
            'behavior_spotted' => implode(', ', $behaviors->all()),
            'brief_description' => $data['brief_description'],
            'concern' => implode(', ', $data['referral_category']),
            'description' => $data['brief_description'],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Referral saved successfully.',
            'consultation' => $consultation,
        ]);
    }

    /**
     * Assign an appointment datetime for a consultation (Guidance/Admin only).
     */
    public function assign(Request $request, $id)
    {
        $user = $request->user();
        // numeric role_id check: allow only Administrator (1) or Guidance (17)
        $roleIds = array_filter(array_map('intval', array_map('trim', explode(',', $user->role_id ?? ''))));
        if (! ($user && (in_array(1, $roleIds) || in_array(17, $roleIds)))) {
            abort(403, 'Forbidden');
        }

        $data = $request->validate([
            'date_time_assigned' => ['required', 'date'],
            'adviser_id' => ['nullable','integer','exists:users,id'],
        ]);

        // server-side check: not in the past
        $assigned = Carbon::parse($data['date_time_assigned']);
        if ($assigned->lt(Carbon::now())) {
            return response()->json(['message' => 'Selected date/time cannot be in the past.'], 422);
        }

        $consult = GuidanceConsultation::find($id);
        if (! $consult) {
            return response()->json(['message' => 'Consultation not found.'], 404);
        }

        $consult->date_time_assigned = $assigned->toDateTimeString();
        $consult->status = 'scheduled';
        $consult->teacher = $data['adviser_id'] ?? null;
        $consult->save();

        // determine student email and name
        $studentEmail = null;
        $studentName = null;
        if (! empty($consult->requestor_id)) {
            $student = DB::table('students')->where('id', $consult->requestor_id)->first();
            if ($student) {
                $studentEmail = $student->student_email ?? $student->email ?? null;
                // resolve name similar to other code
                $lastnameCols = ['lastname', 'last_name', 'lname'];
                $firstnameCols = ['firstname', 'first_name', 'fname'];
                $last = null; $first = null;
                foreach ($lastnameCols as $c) { if (isset($student->$c) && $student->$c) { $last = $student->$c; break; } }
                foreach ($firstnameCols as $c) { if (isset($student->$c) && $student->$c) { $first = $student->$c; break; } }
                if ($last || $first) {
                    $studentName = trim(($last ? $last . ', ' : '') . ($first ?? ''));
                } else {
                    $studentName = $student->name ?? null;
                }
            } else {
                $userRow = DB::table('users')->where('id', $consult->requestor_id)->first();
                if ($userRow) {
                    $studentEmail = $userRow->email ?? null;
                    $studentName = $userRow->name ?? null;
                }
            }
        }

        $isReferred = strtolower((string) ($consult->consultation_type ?? '')) === 'referred';

        // send email if we have an email
        if ($studentEmail) {
            try {
                if ($isReferred) {
                    Mail::send('emails.guidance.referred_student_appointment_scheduled', ['studentName' => $studentName, 'consult' => $consult], function ($m) use ($studentEmail) {
                        $m->to($studentEmail)->subject('Guidance Consultation Appointment');
                    });
                } else {
                    Mail::send('emails.guidance.appointment_scheduled', ['studentName' => $studentName, 'consult' => $consult], function ($m) use ($studentEmail) {
                        $m->to($studentEmail)->subject('Consultation Appointment Schedule');
                    });
                }
            } catch (\Throwable $e) {
                logger()->error('Failed to send appointment email', ['error' => $e->getMessage(), 'consultation_id' => $consult->id]);
                if ($request->wantsJson()) {
                    return response()->json(['message' => 'Appointment assigned but failed to send email.'], 500);
                }
                return redirect()->route('guidance.consultations.index')->with('error', 'Appointment assigned but failed to send email.');
            }
        }

        // notify selected adviser if provided
        if (! empty($data['adviser_id'])) {
            $adviser = User::find($data['adviser_id']);
            if ($adviser && $adviser->email) {
                try {
                    if ($isReferred) {
                        Mail::send('emails.guidance.referred_teacher_notification', [
                            'teacherName' => $adviser->name,
                            'studentName' => $studentName,
                            'consult' => $consult,
                        ], function ($m) use ($adviser) {
                            $m->to($adviser->email)->subject('Your Student is Referred to the Guidance Office');
                        });
                    } else {
                        $assignedName = null;
                        if (! empty($consult->assigned_personnel)) {
                            $u = DB::table('users')->where('id', $consult->assigned_personnel)->first();
                            if ($u) $assignedName = $u->name ?? null;
                        }
                        Mail::send('emails.guidance.adviser_notification', [
                            'adviserName' => $adviser->name,
                            'studentName' => $studentName,
                            'concern' => $consult->concern,
                            'requested' => $consult->date_time_preferred,
                            'assignedPersonnel' => $assignedName,
                            'consult' => $consult,
                        ], function ($m) use ($adviser) {
                            $m->to($adviser->email)->subject('Your advisee has requested a guidance consultation');
                        });
                    }
                } catch (\Throwable $e) {
                    logger()->error('Failed to send adviser notification', ['error' => $e->getMessage(), 'consultation_id' => $consult->id, 'adviser_id' => $data['adviser_id']]);
                }
            }
        }

        // prepare updated consultation data for JSON responses
        $consultData = $consult->toArray();
        $consultData['requestor_name'] = null;
        $consultData['assigned_personnel_name'] = null;
        // try to resolve requestor name
        $student = DB::table('students')->where('id', $consult->requestor_id)->first();
        if ($student) {
            if (isset($student->lastname) || isset($student->firstname)) {
                $last = $student->lastname ?? ($student->last_name ?? null);
                $first = $student->firstname ?? ($student->first_name ?? null);
                $consultData['requestor_name'] = trim(($last ? $last . ', ' : '') . ($first ?? '')) ?: ($student->name ?? null);
            } else {
                $consultData['requestor_name'] = $student->name ?? null;
            }
        } else {
            $userRow = DB::table('users')->where('id', $consult->requestor_id)->first();
            if ($userRow) $consultData['requestor_name'] = $userRow->name ?? null;
        }

        // resolve assigned personnel name
        if (!empty($consult->assigned_personnel)) {
            $u = DB::table('users')->where('id', $consult->assigned_personnel)->first();
            if ($u) $consultData['assigned_personnel_name'] = $u->name ?? null;
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Appointment assigned and student notified.', 'consultation' => $consultData]);
        }

        return redirect()->route('guidance.consultations.index')->with('success', 'Appointment assigned and student notified.');
    }

    /**
     * Save intervention details for a consultation (Guidance/Admin only).
     */
    public function intervention(Request $request, $id)
    {
        $user = $request->user();
        $roleIds = array_filter(array_map('intval', array_map('trim', explode(',', $user->role_id ?? ''))));
        if (! ($user && (in_array(1, $roleIds) || in_array(17, $roleIds)))) {
            abort(403, 'Forbidden');
        }

        $data = $request->validate([
            'intervention' => ['required', 'string'],
            'status' => ['required', 'string', 'in:For Follow-up,For Monitoring,Done Intervention,Refer to School Psychologist'],
            'followup_date' => ['nullable', 'date'],
            'teacher' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $consult = GuidanceConsultation::find($id);
        if (! $consult) {
            return response()->json(['message' => 'Consultation not found.'], 404);
        }
        // if status requires follow-up, ensure followup_date present
        if ($data['status'] === 'For Follow-up' && empty($data['followup_date'])) {
            return response()->json(['message' => 'Follow-up date is required for For Follow-up status.'], 422);
        }

        try {
            // encrypt the intervention details for privacy
            $encrypted = Crypt::encryptString($data['intervention']);
            $consult->intervention = $encrypted;
            $consult->status = $data['status'];
            // set followup_date if provided and status is For Follow-up
            if ($data['status'] === 'For Follow-up') {
                $consult->followup_date = $data['followup_date'];
            } else {
                $consult->followup_date = null;
            }
            $consult->teacher = $data['teacher'] ?? null;
            $consult->save();
        } catch (\Throwable $e) {
            logger()->error('Failed to save intervention', ['error' => $e->getMessage(), 'consultation_id' => $id]);
            return response()->json(['message' => 'Failed to save intervention.'], 500);
        }

        // determine student email and name for possible follow-up notification
        $studentEmail = null;
        $studentName = null;
        if (! empty($consult->requestor_id)) {
            $student = DB::table('students')->where('id', $consult->requestor_id)->first();
            if ($student) {
                $studentEmail = $student->student_email ?? $student->email ?? null;
                $lastnameCols = ['lastname', 'last_name', 'lname'];
                $firstnameCols = ['firstname', 'first_name', 'fname'];
                $last = null; $first = null;
                foreach ($lastnameCols as $c) { if (isset($student->$c) && $student->$c) { $last = $student->$c; break; } }
                foreach ($firstnameCols as $c) { if (isset($student->$c) && $student->$c) { $first = $student->$c; break; } }
                if ($last || $first) {
                    $studentName = trim(($last ? $last . ', ' : '') . ($first ?? ''));
                } else {
                    $studentName = $student->name ?? null;
                }
            } else {
                $userRow = DB::table('users')->where('id', $consult->requestor_id)->first();
                if ($userRow) {
                    $studentEmail = $userRow->email ?? null;
                    $studentName = $userRow->name ?? null;
                }
            }
        }

        // prepare minimal updated consultation data
        $consultData = $consult->toArray();
        $consultData['requestor_name'] = null;
        $consultData['assigned_personnel_name'] = null;
        $student = DB::table('students')->where('id', $consult->requestor_id)->first();
        if ($student) {
            if (isset($student->lastname) || isset($student->firstname)) {
                $last = $student->lastname ?? ($student->last_name ?? null);
                $first = $student->firstname ?? ($student->first_name ?? null);
                $consultData['requestor_name'] = trim(($last ? $last . ', ' : '') . ($first ?? '')) ?: ($student->name ?? null);
            } else {
                $consultData['requestor_name'] = $student->name ?? null;
            }
        } else {
            $userRow = DB::table('users')->where('id', $consult->requestor_id)->first();
            if ($userRow) $consultData['requestor_name'] = $userRow->name ?? null;
        }
        if (!empty($consult->assigned_personnel)) {
            $u = DB::table('users')->where('id', $consult->assigned_personnel)->first();
            if ($u) $consultData['assigned_personnel_name'] = $u->name ?? null;
        }

        // Do not return the decrypted intervention contents in JSON response
        $consultData['intervention'] = null;

        // if For Follow-up, send follow-up scheduled email to requestor
        if ($data['status'] === 'For Follow-up' && $studentEmail) {
            try {
                Mail::send('emails.guidance.followup_scheduled', [
                    'studentName' => $studentName,
                    'consult' => $consult,
                    'followupDate' => $consult->followup_date,
                ], function ($m) use ($studentEmail) {
                    $m->to($studentEmail)->subject('Follow-up Consultation Scheduled');
                });
            } catch (\Throwable $e) {
                logger()->error('Failed to send follow-up email', ['error' => $e->getMessage(), 'consultation_id' => $consult->id]);
            }
        }

        return response()->json(['message' => 'Intervention recorded.', 'consultation' => $consultData]);
    }

    /**
     * Return decrypted intervention details for editing (Guidance/Admin only).
     */
    public function getIntervention(Request $request, $id)
    {
        $user = $request->user();
        $roleIds = array_filter(array_map('intval', array_map('trim', explode(',', $user->role_id ?? ''))));
        if (! ($user && (in_array(1, $roleIds) || in_array(17, $roleIds)))) {
            abort(403, 'Forbidden');
        }

        $consult = GuidanceConsultation::find($id);
        if (! $consult) {
            return response()->json(['message' => 'Consultation not found.'], 404);
        }

        $plain = '';
        if (! empty($consult->intervention)) {
            try {
                $plain = Crypt::decryptString($consult->intervention);
            } catch (\Throwable $e) {
                logger()->error('Failed to decrypt intervention', ['error' => $e->getMessage(), 'consultation_id' => $consult->id]);
                // fallback: return empty string to avoid exposing errors
                $plain = '';
            }
        }

        return response()->json([
            'intervention' => $plain,
            'status' => $consult->status,
            'followup_date' => $consult->followup_date,
            'consultation' => $consult->toArray(),
        ]);
    }

    /**
     * Render printable admission slip for a consultation.
     */
    public function admissionSlip(Request $request, $id)
    {
        $user = $request->user();
        $roleIds = array_filter(array_map('intval', array_map('trim', explode(',', $user->role_id ?? ''))));
        if (! ($user && (in_array(1, $roleIds) || in_array(17, $roleIds)))) {
            abort(403, 'Forbidden');
        }

        $consult = GuidanceConsultation::find($id);
        if (! $consult) {
            abort(404, 'Consultation not found');
        }

        // Resolve student/requestor name and optionally grade/section from students table
        $studentName = null;
        $gradeSection = '';
        if (! empty($consult->requestor_id)) {
            $student = DB::table('students')->where('id', $consult->requestor_id)->first();
            if ($student) {
                $lastnameCols = ['lastname', 'last_name', 'lname'];
                $firstnameCols = ['firstname', 'first_name', 'fname'];
                $last = null; $first = null;
                foreach ($lastnameCols as $c) { if (isset($student->$c) && $student->$c) { $last = $student->$c; break; } }
                foreach ($firstnameCols as $c) { if (isset($student->$c) && $student->$c) { $first = $student->$c; break; } }
                if ($last || $first) {
                    $studentName = trim(($last ? $last . ', ' : '') . ($first ?? ''));
                } else {
                    $studentName = $student->name ?? null;
                }
                // Resolve grade & section similar to Clinic consultations
                $grade = '';
                $sectionName = '';

                $sectionStudent = DB::table('section_students')
                    ->where('studentid', $student->id)
                    ->orderByDesc('id')
                    ->first();

                if ($sectionStudent) {
                    $levelId = $sectionStudent->levelid ?? null;
                    if (! empty($levelId)) {
                        $grade = 'Grade ' . $levelId;
                    }

                    if (! empty($sectionStudent->sectionid)) {
                        $section = DB::table('sections')->where('id', $sectionStudent->sectionid)->first();
                        if ($section) {
                            foreach (['sectionname', 'section_name', 'name', 'section'] as $column) {
                                if (isset($section->$column) && $section->$column !== null && $section->$column !== '') {
                                    $sectionName = $section->$column;
                                    break;
                                }
                            }
                        }
                    }
                }

                // Fallback to direct student fields if not found from section tables
                if ($grade === '' && isset($student->grade) && $student->grade !== null && $student->grade !== '') {
                    $grade = is_numeric($student->grade) ? ('Grade ' . $student->grade) : $student->grade;
                }
                if ($sectionName === '' && isset($student->section) && $student->section !== null && $student->section !== '') {
                    $sectionName = $student->section;
                }

                $gradeSection = trim($grade . ($sectionName ? ' ' . $sectionName : ''));
            } else {
                $userRow = DB::table('users')->where('id', $consult->requestor_id)->first();
                if ($userRow) {
                    $studentName = $userRow->name ?? null;
                }
            }
        }

        // Use assigned datetime or created_at as visit timestamp
        $visitAt = $consult->date_time_assigned ?: $consult->created_at;

        $teacherName = null;
        if (! empty($consult->teacher)) {
            $teacher = DB::table('users')->where('id', $consult->teacher)->first();
            if ($teacher) {
                $teacherName = $teacher->name ?? null;
            }
        }

        $counselorName = null;
        if (! empty($consult->assigned_personnel)) {
            $counselor = DB::table('users')->where('id', $consult->assigned_personnel)->first();
            if ($counselor) {
                $counselorName = $counselor->name ?? null;
            }
        }

        return view('guidance.admission_slip', [
            'consult' => $consult,
            'studentName' => $studentName,
            'gradeSection' => $gradeSection,
            'visitAt' => $visitAt,
            'teacherName' => $teacherName,
            'counselorName' => $counselorName ?? ($request->user()->name ?? null),
        ]);
    }
}
