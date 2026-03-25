<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Consultation;
use App\Models\User;
use App\Models\PhysicianSchedule;
use Illuminate\Support\Facades\Mail;
use App\Mail\ConsultationCreatedMail;
use App\Mail\ConsultationScheduledMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class ConsultationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        // Build base query; Nurses and admins see all; others only their own
        if ($user->hasAnyRole(['Administrator', 'Nurse', 'Clinic'])) {
            $query = Consultation::query();
        } else {
            // Prefer requestor_id (added by migrations). Fall back to old `requestor` string column if necessary.
            if (Schema::hasColumn('consultations', 'requestor_id')) {
                $query = Consultation::where('requestor_id', $user->id);
            } elseif (Schema::hasColumn('consultations', 'requestor')) {
                // Some installations may still store requestor as email or name in `requestor` column
                $query = Consultation::where('requestor', $user->email ?? $user->name ?? $user->id);
            } else {
                // Last resort: no clear linking column, return empty query scoped to impossible id
                $query = Consultation::whereRaw('0 = 1');
            }
        }

        // Search support: simple server-side search across requestor and reason
        $search = $request->input('q') ?? $request->input('search') ?? null;
        if ($search) {
            $query->where(function ($q) use ($search) {
                // If the consultations table still has a `requestor` string column, search it directly
                if (Schema::hasColumn('consultations', 'requestor')) {
                    $q->where('requestor', 'like', "%{$search}%");
                } else {
                    // Try to match students by name or pisay id and filter by requestor_id
                    // Build a safe students search that only references columns that exist
                    $studentQuery = DB::table('students');

                    $nameCols = [];
                    foreach (['lastname','firstname','middle_name','name'] as $c) {
                        if (Schema::hasColumn('students', $c)) {
                            $nameCols[] = "IFNULL(" . $c . ", '')";
                        }
                    }

                    $pisayCols = [];
                    foreach (['pisaysystemid','pisay_id','pisay_system_id'] as $c) {
                        if (Schema::hasColumn('students', $c)) {
                            $pisayCols[] = "IFNULL(" . $c . ", '')";
                        }
                    }

                    $lower = strtolower($search);
                    if (!empty($nameCols) || !empty($pisayCols)) {
                        $studentQuery->where(function ($sq) use ($nameCols, $pisayCols, $lower) {
                            if (!empty($nameCols)) {
                                $sq->whereRaw('LOWER(CONCAT_WS(" ", ' . implode(', ', $nameCols) . ')) LIKE ?', ["%{$lower}%"]);
                            }
                            if (!empty($pisayCols)) {
                                if (!empty($nameCols)) {
                                    $sq->orWhereRaw('LOWER(CONCAT_WS(" ", ' . implode(', ', $pisayCols) . ')) LIKE ?', ["%{$lower}%"]);
                                } else {
                                    $sq->whereRaw('LOWER(CONCAT_WS(" ", ' . implode(', ', $pisayCols) . ')) LIKE ?', ["%{$lower}%"]);
                                }
                            }
                        });
                    } else {
                        // If no useful student columns exist, make sure query returns empty set
                        $studentQuery->whereRaw('0 = 1');
                    }

                    $studentIds = $studentQuery->pluck('id')->toArray();

                    // Also search employee/user names and include their IDs
                    $userIds = DB::table('users')
                        ->whereRaw('LOWER(name) LIKE ?', ["%{$lower}%"])
                        ->pluck('id')
                        ->toArray();

                    $combinedIds = array_values(array_unique(array_merge($studentIds, $userIds)));

                    if (!empty($combinedIds)) {
                        $q->whereIn('requestor_id', $combinedIds);
                    }
                }

                // Also search reason and status
                $q->orWhere('reason', 'like', "%{$search}%")
                  ->orWhere('status', 'like', "%{$search}%");
            });
        }

        $query->latest();

        // Paginate 10 per page and keep query string
        $consultations = $query->paginate(10)->appends($request->only('q'));

        // Enrich consultations with requestor details for display
        $consultations->transform(function ($consult) {
            $requestor = [
                'name' => $consult->requestor ?? null,
                'sex' => null,
                'grade_level' => null,
                'section' => null,
                'office' => null,
            ];

            if (!empty($consult->requestor_type) && $consult->requestor_type === 'student' && !empty($consult->requestor_id)) {
                $student = DB::table('students')->where('id', $consult->requestor_id)->first();
                if ($student) {
                    // Prefer explicit lastname, firstname fields when available
                    $lastnameCols = ['lastname', 'last_name', 'lname'];
                    $firstnameCols = ['firstname', 'first_name', 'fname'];
                    $last = null;
                    $first = null;
                    foreach ($lastnameCols as $c) {
                        if (isset($student->$c) && $student->$c !== null && $student->$c !== '') {
                            $last = $student->$c;
                            break;
                        }
                    }
                    foreach ($firstnameCols as $c) {
                        if (isset($student->$c) && $student->$c !== null && $student->$c !== '') {
                            $first = $student->$c;
                            break;
                        }
                    }

                    if ($last || $first) {
                        $nameParts = [];
                        if ($last) $nameParts[] = $last;
                        if ($first) $nameParts[] = $first;
                        // Format as "lastname, firstname" when both present
                        $requestor['name'] = $last && $first ? ($last . ', ' . $first) : implode(' ', $nameParts);
                    } else {
                        $requestor['name'] = $student->name ?? $requestor['name'];
                    }

                    $requestor['sex'] = $student->sex ?? null;

                    // Attach photo path if available
                    $photoField = null;
                    foreach (['img','image','photo','student_image'] as $pf) {
                        if (isset($student->$pf) && $student->$pf) {
                            $photoField = $student->$pf;
                            break;
                        }
                    }
                    if ($photoField) {
                        $requestor['photo'] = '/storage/students_profile_picture/' . rawurlencode($photoField);
                    }

                    $sectionStudent = DB::table('section_students')->where('studentid', $student->id)->orderByDesc('id')->first();
                    if ($sectionStudent) {
                        $levelId = $sectionStudent->levelid ?? null;
                        if ($levelId) {
                            $requestor['grade_level'] = 'Grade ' . $levelId;
                        }
                        $section = DB::table('sections')->where('id', $sectionStudent->sectionid)->first();
                        if ($section) {
                            // sections table may use different column names; try common variants
                            $sectionName = null;
                            foreach (['sectionname','section_name','name','section'] as $col) {
                                if (isset($section->$col) && $section->$col !== null && $section->$col !== '') {
                                    $sectionName = $section->$col;
                                    break;
                                }
                            }
                            $requestor['section'] = $sectionName ?? null;
                        }
                    }
                }
            } else {
                if (!empty($consult->requestor_id)) {
                    $u = DB::table('users')->where('id', $consult->requestor_id)->first();
                    if ($u) {
                        $requestor['name'] = $u->name ?? $requestor['name'];
                        $requestor['sex'] = $u->sex ?? null;
                        $requestor['office'] = $u->office ?? null;
                        // Attach user photo if present (common fields)
                        foreach (['profile_picture','profile_photo','image','avatar','photo'] as $pf) {
                            if (isset($u->$pf) && $u->$pf) {
                                $requestor['photo'] = '/storage/' . rawurlencode($u->$pf);
                                break;
                            }
                        }
                    }
                }
            }

            $consult->requestor_data = (object) $requestor;

            // Build a user-friendly scheduled date display.
            // When the consultation status is Scheduled, prefer the selected `physician_schedule` (via `schedule_id`) so
            // the UI shows the actual appointment slot the user chose; fall back to `scheduled_at` if no schedule exists.
            $dateDisplay = null;
            if (!empty($consult->status) && strtolower($consult->status) === 'scheduled') {
                if (!empty($consult->schedule_id)) {
                    $sched = DB::table('physician_schedule')->where('id', $consult->schedule_id)->first();
                    if ($sched && !empty($sched->schedule_date)) {
                        $date = Carbon::parse($sched->schedule_date)->toFormattedDateString();
                        $start = $sched->time_start ?? null;
                        $end = $sched->time_end ?? null;
                        if ($start) {
                            try {
                                $start = Carbon::parse($start)->format('g:i A');
                            } catch (\Throwable $e) {
                                // keep original if parse fails
                            }
                        }
                        if ($end) {
                            try {
                                $end = Carbon::parse($end)->format('g:i A');
                            } catch (\Throwable $e) {
                                // keep original if parse fails
                            }
                        }
                        if ($start && $end) {
                            $date .= ' · ' . $start . ' - ' . $end;
                        } elseif ($start) {
                            $date .= ' · ' . $start;
                        }
                        $dateDisplay = $date;
                    }
                }

                if ((!isset($dateDisplay) || !$dateDisplay) && !empty($consult->scheduled_at)) {
                    try {
                        $dateDisplay = Carbon::parse($consult->scheduled_at)->toDayDateTimeString();
                    } catch (\Throwable $e) {
                        $dateDisplay = Carbon::parse($consult->scheduled_at)->toFormattedDateString();
                    }
                }
            } else {
                // Non-scheduled consultations: prefer a linked physician schedule when available, otherwise show scheduled_at
                if (!empty($consult->schedule_id)) {
                    $sched = DB::table('physician_schedule')->where('id', $consult->schedule_id)->first();
                    if ($sched && !empty($sched->schedule_date)) {
                        $date = Carbon::parse($sched->schedule_date)->toFormattedDateString();
                        $start = $sched->time_start ?? null;
                        $end = $sched->time_end ?? null;
                        if ($start) {
                            try {
                                $start = Carbon::parse($start)->format('g:i A');
                            } catch (\Throwable $e) {
                                // keep original if parse fails
                            }
                        }
                        if ($end) {
                            try {
                                $end = Carbon::parse($end)->format('g:i A');
                            } catch (\Throwable $e) {
                                // keep original if parse fails
                            }
                        }
                        if ($start && $end) {
                            $date .= ' · ' . $start . ' - ' . $end;
                        } elseif ($start) {
                            $date .= ' · ' . $start;
                        }
                        $dateDisplay = $date;
                    }
                }

                if ((!isset($dateDisplay) || !$dateDisplay) && !empty($consult->scheduled_at)) {
                    try {
                        $dateDisplay = Carbon::parse($consult->scheduled_at)->toDayDateTimeString();
                    } catch (\Throwable $e) {
                        $dateDisplay = Carbon::parse($consult->scheduled_at)->toFormattedDateString();
                    }
                }
            }

            // Also produce two separate display fields for the UI:
            // - selected_schedule_display: the physician schedule slot the user selected (from schedule_id), if any
            // - actual_scheduled_display: the actual scheduled_at value set by the nurse when scheduling
            $selectedScheduleDisplay = null;
            if (!empty($consult->schedule_id)) {
                $sched = DB::table('physician_schedule')->where('id', $consult->schedule_id)->first();
                if ($sched && !empty($sched->schedule_date)) {
                    $sdate = Carbon::parse($sched->schedule_date)->toFormattedDateString();
                    $sstart = $sched->time_start ?? null;
                    $send = $sched->time_end ?? null;
                    if ($sstart) {
                        try { $sstart = Carbon::parse($sstart)->format('g:i A'); } catch (\Throwable $e) { }
                    }
                    if ($send) {
                        try { $send = Carbon::parse($send)->format('g:i A'); } catch (\Throwable $e) { }
                    }
                    if ($sstart && $send) {
                        $sdate .= ' · ' . $sstart . ' - ' . $send;
                    } elseif ($sstart) {
                        $sdate .= ' · ' . $sstart;
                    }
                    $selectedScheduleDisplay = $sdate;
                }
            }

            $actualScheduledDisplay = null;
            if (!empty($consult->scheduled_at)) {
                try {
                    $actualScheduledDisplay = Carbon::parse($consult->scheduled_at)->toDayDateTimeString();
                } catch (\Throwable $e) {
                    $actualScheduledDisplay = Carbon::parse($consult->scheduled_at)->toFormattedDateString();
                }
            }

            $consult->selected_schedule_display = $selectedScheduleDisplay;
            $consult->actual_scheduled_display = $actualScheduledDisplay;
            $consult->date_scheduled_display = $dateDisplay;
            return $consult;
        });

        // Provide upcoming physician schedules for appointment creation (no past dates)
        $today = Carbon::now('Asia/Manila')->toDateString();
        $physicianSchedules = PhysicianSchedule::whereDate('schedule_date', '>=', $today)
            ->orderBy('schedule_date')
            ->orderBy('time_start')
            ->get();

        return Inertia::render('Health/Consultations/Index', [
            'consultations' => $consultations,
            'physicianSchedules' => $physicianSchedules,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
            'contact' => 'nullable|string|max:100',
            'consultation_type' => 'nullable|in:walk-in,scheduled',
            'physician_schedule_id' => 'nullable|integer|exists:physician_schedule,id',
            'scheduled_at' => 'nullable|date',
        ]);

        $user = $request->user();
        $data = $request->only(['reason','contact','consultation_type','physician_schedule_id','scheduled_at']);

        // Prefer explicit requestor_id/requestor_type from the request (e.g., staff-created appointments).
        if ($request->filled('requestor_id')) {
            $data['requestor_id'] = $request->input('requestor_id');
            $data['requestor_type'] = $request->input('requestor_type') ?? '';
        }
        // Support nurse/admin creating a student appointment: frontend may send `requestor_type=student` and
        // `requestor` (PISAY) or `pisay`. Try to resolve to a student record; if found, set requestor_id to the student id.
        elseif ($request->filled('requestor_type') && $request->input('requestor_type') === 'student') {
            $pisay = $request->input('requestor') ?? $request->input('pisay') ?? null;
            $foundStudent = null;
            if ($pisay) {
                $studentQuery = DB::table('students');
                // check common pisay column names
                $pisayCols = ['pisaysystemid','pisay_id','pisay_system_id'];
                $first = true;
                foreach ($pisayCols as $c) {
                    if (Schema::hasColumn('students', $c)) {
                        if ($first) { $studentQuery->where($c, $pisay); $first = false; }
                        else { $studentQuery->orWhere($c, $pisay); }
                    }
                }
                if (! $first) {
                    $foundStudent = $studentQuery->first();
                }
            }

            if ($foundStudent) {
                $data['requestor_id'] = $foundStudent->id;
                $data['requestor_type'] = 'student';
                // clear free-text requestor field to avoid confusion if column exists
                if (Schema::hasColumn('consultations', 'requestor')) {
                    $data['requestor'] = null;
                }
            } else {
                // no matching student record — store the provided PISAY in the `requestor` string field (if column exists)
                if (Schema::hasColumn('consultations', 'requestor')) {
                    $data['requestor'] = $pisay ?? $request->input('requestor') ?? '';
                }
                $data['requestor_id'] = null;
                $data['requestor_type'] = 'student';
            }
        }
        elseif ($user) {
            // For authenticated users, store the user's id as requestor_id and mark as employee
            $data['requestor_id'] = $user->id;
            $data['requestor_type'] = 'employee';
        }

        // Normalize schedule fields: map incoming physician_schedule_id to schedule_id
        if (!empty($data['physician_schedule_id'])) {
            $data['schedule_id'] = $data['physician_schedule_id'];
            unset($data['physician_schedule_id']);
            // derive scheduled_at from the schedule if not explicitly provided
            if (empty($data['scheduled_at'])) {
                $ps = PhysicianSchedule::find($data['schedule_id']);
                if ($ps && !empty($ps->schedule_date)) {
                    try {
                        $dt = Carbon::parse($ps->schedule_date . ' ' . ($ps->time_start ?? '00:00:00'));
                        $data['scheduled_at'] = $dt->toDateTimeString();
                    } catch (\Throwable $e) {
                        // ignore parse errors
                    }
                }
            }
        }

        // Determine default consultation_type and status
        $ctype = $data['consultation_type'] ?? 'walk-in';
        $status = $ctype === 'walk-in' ? 'active' : 'Pending';

        $consult = Consultation::create(array_merge($data, ['consultation_type' => $ctype, 'status' => $status]));

        // Notify nurses and nurse-like positions (copy logic from ClinicKiosk)
        try {
            // Build requestor info (prefer student details when available)
            $requestor = [
                'name' => $consult->requestor ?? null,
                'sex' => null,
                'grade_level' => null,
                'section' => null,
                'office' => null,
            ];

            if (!empty($consult->requestor_type) && $consult->requestor_type === 'student' && !empty($consult->requestor_id)) {
                $student = DB::table('students')->where('id', $consult->requestor_id)->first();
                if ($student) {
                    $lastnameCols = ['lastname', 'last_name', 'lname'];
                    $firstnameCols = ['firstname', 'first_name', 'fname'];
                    $last = null; $first = null;
                    foreach ($lastnameCols as $c) { if (isset($student->$c) && $student->$c !== null && $student->$c !== '') { $last = $student->$c; break; } }
                    foreach ($firstnameCols as $c) { if (isset($student->$c) && $student->$c !== null && $student->$c !== '') { $first = $student->$c; break; } }
                    if ($last || $first) {
                        $nameParts = [];
                        if ($last) $nameParts[] = $last;
                        if ($first) $nameParts[] = $first;
                        $requestor['name'] = $last && $first ? ($last . ', ' . $first) : implode(' ', $nameParts);
                    } else {
                        $requestor['name'] = $student->name ?? $requestor['name'];
                    }
                    $requestor['sex'] = $student->sex ?? null;
                    $sectionStudent = DB::table('section_students')->where('studentid', $student->id)->orderByDesc('id')->first();
                    if ($sectionStudent) {
                        $levelId = $sectionStudent->levelid ?? null;
                        if ($levelId) $requestor['grade_level'] = 'Grade ' . $levelId;
                        $section = DB::table('sections')->where('id', $sectionStudent->sectionid)->first();
                        if ($section) {
                            foreach (['sectionname','section_name','name','section'] as $col) {
                                if (isset($section->$col) && $section->$col !== null && $section->$col !== '') { $requestor['section'] = $section->$col; break; }
                            }
                        }
                    }
                }
            } else {
                if (!empty($consult->requestor_id)) {
                    $u = DB::table('users')->where('id', $consult->requestor_id)->first();
                    if ($u) {
                        $requestor['name'] = $u->name ?? $requestor['name'];
                        $requestor['sex'] = $u->sex ?? null;
                        $requestor['office'] = $u->office ?? null;
                    }
                }
            }

            $dateScheduled = null;
            if (!empty($consult->scheduled_at)) {
                $dateScheduled = Carbon::parse($consult->scheduled_at)->toDayDateTimeString();
            }

            $nurseRole = \App\Models\Role::whereRaw('LOWER(name) = ?', ['nurse'])->first();
            $nurseRecipients = User::where(function($q) use ($nurseRole) {
                if ($nurseRole) $q->whereRaw('FIND_IN_SET(?, role_id)', [$nurseRole->id]);
                $q->orWhereRaw('LOWER(position) LIKE ?', ['%nurse%']);
            })->pluck('email')->filter()->unique();

            foreach ($nurseRecipients as $email) {
                try {
                    Mail::send('emails.clinic.consultation_logged', ['consult' => $consult, 'requestor' => (object) $requestor, 'dateScheduled' => $dateScheduled], function ($m) use ($email) {
                        $m->to($email)->subject('New Clinic Consultation Logged');
                    });
                } catch (\Throwable $e) {
                    logger()->error('Failed to send clinic consultation email to '.$email.': '.$e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to prepare/send clinic consultation emails: '.$e->getMessage());
        }

        return redirect()->route('consultations.index')->with('success','Consultation requested');
    }

    public function update(Request $request, Consultation $consultation)
    {
        $user = $request->user();

        // Only Nurse, Clinic, or Admin can schedule
        if (! $user->hasAnyRole(['Administrator', 'Nurse', 'Clinic'])) {
            abort(403);
        }

        // Support two update flows:
        // 1) scheduling: request includes `scheduled_at` (date) and optional `notes` -> sets status to Scheduled
        // 2) vitals editing: request includes vitals/medicines/time_start/time_out etc. -> update those fields

        $scheduling = $request->has('scheduled_at');
        $vitalsKeys = ['temperature','temp','pulse_rate','pulse','heart_rate','oxygen','oxygen_saturation','spo2','blood_pressure','bp','action_taken','medicine_given','medicines','time_start','time_out','disposition','date_attended'];
        $editingVitals = false;
        foreach ($vitalsKeys as $k) { if ($request->has($k)) { $editingVitals = true; break; } }

        if ($scheduling) {
            $rules = ['scheduled_at' => 'required|date'];
            if (Schema::hasColumn('consultations', 'notes')) {
                $rules['notes'] = 'nullable|string|max:1000';
            }
            $request->validate($rules);

            $consultation->scheduled_at = $request->input('scheduled_at');
            if (Schema::hasColumn('consultations', 'notes')) {
                $consultation->notes = $request->input('notes');
            }
            $consultation->status = 'Scheduled';
        }

        if ($editingVitals) {
            // validate common vitals fields (all nullable)
            $vRules = [
                'temperature' => 'nullable|numeric',
                'pulse_rate' => 'nullable|integer',
                'oxygen' => 'nullable|integer',
                'blood_pressure' => 'nullable|string|max:50',
                'medicine_given' => 'nullable|string|max:1000',
                'medicines' => 'nullable|string|max:1000',
                'action_taken' => 'nullable|string|max:1000',
                'time_start' => 'nullable|date_format:Y-m-d\TH:i',
                'time_out' => 'nullable|date_format:Y-m-d\\TH:i',
                'date_attended' => 'nullable|date_format:Y-m-d\\TH:i',
                'disposition' => 'nullable|string|max:255',
            ];
            // Ensure disposition does not exceed database column length to avoid validation failure.
            if ($request->has('disposition')) {
                $d = $request->input('disposition');
                if (is_string($d) && mb_strlen($d) > 255) {
                    $request->merge(['disposition' => mb_substr($d, 0, 255)]);
                }
            }

            $request->validate($vRules);

            if ($request->filled('temperature')) $consultation->temperature = $request->input('temperature');
            if ($request->filled('temp')) $consultation->temperature = $request->input('temp');
            if ($request->filled('pulse_rate')) $consultation->pulse_rate = $request->input('pulse_rate');
            if ($request->filled('pulse')) $consultation->pulse_rate = $request->input('pulse');
            if ($request->filled('heart_rate')) $consultation->pulse_rate = $request->input('heart_rate');
            if ($request->filled('oxygen')) $consultation->oxygen = $request->input('oxygen');
            if ($request->filled('oxygen_saturation')) $consultation->oxygen = $request->input('oxygen_saturation');
            if ($request->filled('spo2')) $consultation->oxygen = $request->input('spo2');
            if ($request->filled('blood_pressure')) $consultation->blood_pressure = $request->input('blood_pressure');
            if ($request->filled('bp')) $consultation->blood_pressure = $request->input('bp');
            if ($request->filled('medicine_given')) $consultation->medicine_given = $request->input('medicine_given');
            if ($request->filled('medicines')) $consultation->medicine_given = $request->input('medicines');
            if ($request->filled('action_taken')) $consultation->action_taken = $request->input('action_taken');
            if ($request->filled('time_start')) $consultation->time_start = Carbon::parse($request->input('time_start'))->toDateTimeString();
            if ($request->filled('time_out')) $consultation->time_out = Carbon::parse($request->input('time_out'))->toDateTimeString();
            if ($request->filled('date_attended')) $consultation->date_attended = Carbon::parse($request->input('date_attended'))->toDateTimeString();
            if ($request->filled('disposition')) $consultation->disposition = $request->input('disposition');
        }
        // Always set nurse who performed the action
        $consultation->nurse_id = $user->id;

        // If this update came from recording vitals, mark as Completed
        if ($editingVitals) {
            $consultation->status = 'Completed';
        }

        $consultation->save();

        // Only notify requester when this was a scheduling action
        if ($scheduling) {
            try {
                $recipientEmail = null;
                if (!empty($consultation->requestor_type) && $consultation->requestor_type === 'student' && !empty($consultation->requestor_id)) {
                    $student = DB::table('students')->where('id', $consultation->requestor_id)->first();
                    if ($student) {
                        if (isset($student->student_email) && $student->student_email) {
                            $recipientEmail = $student->student_email;
                        } elseif (isset($student->email) && $student->email) {
                            $recipientEmail = $student->email;
                        }
                    }
                }

                if (! $recipientEmail && !empty($consultation->requestor_id)) {
                    $u = DB::table('users')->where('id', $consultation->requestor_id)->first();
                    if ($u && !empty($u->email)) {
                        $recipientEmail = $u->email;
                    }
                }

                // fallback to consultations.email if present
                if (! $recipientEmail && !empty($consultation->email)) {
                    $recipientEmail = $consultation->email;
                }

                if ($recipientEmail) {
                    Mail::to($recipientEmail)->send(new ConsultationScheduledMail($consultation));
                } else {
                    logger()->warning('No recipient email found for consultation scheduling: id='.$consultation->id);
                }
            } catch (\Throwable $e) {
                logger()->error('Failed to send consultation scheduled email: '.$e->getMessage());
            }

            return redirect()->route('consultations.index')->with('success','Consultation scheduled');
        }

        // For vitals edits / other updates, don't send emails — just redirect with a generic message
        return redirect()->route('consultations.index')->with('success','Consultation updated');
    }

    /**
     * Print a consultation admission slip view.
     */
    public function print(Consultation $consultation)
    {
        // Enrich requestor data (reuse same approach as index transform)
        $requestor = [
            'name' => $consultation->requestor ?? null,
            'sex' => null,
            'grade_level' => null,
            'section' => null,
            'office' => null,
            'photo' => null,
        ];

        if (!empty($consultation->requestor_type) && $consultation->requestor_type === 'student' && !empty($consultation->requestor_id)) {
            $student = DB::table('students')->where('id', $consultation->requestor_id)->first();
            if ($student) {
                $lastnameCols = ['lastname', 'last_name', 'lname'];
                $firstnameCols = ['firstname', 'first_name', 'fname'];
                $last = null; $first = null;
                foreach ($lastnameCols as $c) { if (isset($student->$c) && $student->$c) { $last = $student->$c; break; } }
                foreach ($firstnameCols as $c) { if (isset($student->$c) && $student->$c) { $first = $student->$c; break; } }
                if ($last || $first) {
                    $requestor['name'] = $last && $first ? ($last . ', ' . $first) : trim(($last ?? '') . ' ' . ($first ?? ''));
                } else {
                    $requestor['name'] = $student->name ?? $requestor['name'];
                }
                $requestor['sex'] = $student->sex ?? null;
                $photoField = null;
                foreach (['img','image','photo','student_image'] as $pf) {
                    if (isset($student->$pf) && $student->$pf) { $photoField = $student->$pf; break; }
                }
                if ($photoField) $requestor['photo'] = '/storage/students_profile_picture/' . rawurlencode($photoField);

                $sectionStudent = DB::table('section_students')->where('studentid', $student->id)->orderByDesc('id')->first();
                if ($sectionStudent) {
                    $levelId = $sectionStudent->levelid ?? null;
                    if ($levelId) $requestor['grade_level'] = 'Grade ' . $levelId;
                    $section = DB::table('sections')->where('id', $sectionStudent->sectionid)->first();
                    if ($section) {
                        foreach (['sectionname','section_name','name','section'] as $col) {
                            if (isset($section->$col) && $section->$col) { $requestor['section'] = $section->$col; break; }
                        }
                    }
                }
            }
        } else {
            if (!empty($consultation->requestor_id)) {
                $u = DB::table('users')->where('id', $consultation->requestor_id)->first();
                if ($u) {
                    $requestor['name'] = $u->name ?? $requestor['name'];
                    $requestor['sex'] = $u->sex ?? null;
                    $requestor['office'] = $u->office ?? null;
                    foreach (['profile_picture','profile_photo','image','avatar','photo'] as $pf) {
                        if (isset($u->$pf) && $u->$pf) { $requestor['photo'] = '/storage/' . rawurlencode($u->$pf); break; }
                    }
                }
            }
        }

        $nurse = null;
        if (!empty($consultation->nurse_id)) {
            $nurseRec = DB::table('users')->where('id', $consultation->nurse_id)->first();
            if ($nurseRec) $nurse = $nurseRec->name ?? null;
        }

        return view('consultations.print', [
            'consultation' => $consultation,
            'requestor' => (object)$requestor,
            'nurse' => $nurse,
        ]);
    }

    /**
     * Printable consultation log report (A4 landscape)
     */
    public function logPrint(Request $request)
    {
        $user = $request->user();

        // Only allow clinic/nurse/admin per route middleware but double-check here
        if (! $user->hasAnyRole(['Administrator', 'Nurse', 'Clinic'])) {
            abort(403);
        }

        // Determine which requestor_type to show: default to 'student' but allow route or query override
        $type = $request->query('type', null);
        if (empty($type)) {
            $currentRoute = $request->route() ? $request->route()->getName() : null;
            if ($currentRoute === 'consultations.employee.log.print') {
                $type = 'employee';
            } else {
                $type = 'student';
            }
        }
        if (!in_array($type, ['student', 'employee', 'both'])) {
            $type = 'student';
        }

        // Fetch consultations that are Completed. When a specific type is requested, filter by it.
        $query = Consultation::where('status', 'Completed');
        if ($type !== 'both') {
            $query->where('requestor_type', $type);
        }

        // Optional date range filtering by date_attended (expects YYYY-MM-DD)
        $start = $request->query('start');
        $end = $request->query('end');
        if ($start) {
            try {
                $s = Carbon::parse($start)->toDateString();
                $query->whereDate('date_attended', '>=', $s);
            } catch (\Throwable $e) {
                // invalid start date — ignore and continue without filtering
            }
        }
        if ($end) {
            try {
                $eD = Carbon::parse($end)->toDateString();
                $query->whereDate('date_attended', '<=', $eD);
            } catch (\Throwable $e) {
                // invalid end date — ignore
            }
        }

        $consultations = $query->orderByDesc('date_attended')->get();

        // Enrich each consultation with student details (name, grade_level, section)
        $consultations->transform(function ($consult) {
            $student = null;
            $requestor = [
                'name' => $consult->requestor ?? null,
                'grade_level' => null,
                'section' => null,
                'sex' => null,
            ];

            if (!empty($consult->requestor_id)) {
                $student = DB::table('students')->where('id', $consult->requestor_id)->first();
            }

            if ($student) {
                // name preference: lastname, firstname or name
                $lastnameCols = ['lastname', 'last_name', 'lname'];
                $firstnameCols = ['firstname', 'first_name', 'fname'];
                $last = null; $first = null;
                foreach ($lastnameCols as $c) { if (isset($student->$c) && $student->$c !== null && $student->$c !== '') { $last = $student->$c; break; } }
                foreach ($firstnameCols as $c) { if (isset($student->$c) && $student->$c !== null && $student->$c !== '') { $first = $student->$c; break; } }
                if ($last || $first) {
                    $requestor['name'] = $last && $first ? ($last . ', ' . $first) : trim(($last ?? '') . ' ' . ($first ?? ''));
                } else {
                    $requestor['name'] = $student->name ?? $requestor['name'];
                }

                $requestor['grade_level'] = null;
                $requestor['sex'] = $student->sex ?? null;
                $sectionStudent = DB::table('section_students')->where('studentid', $student->id)->orderByDesc('id')->first();
                if ($sectionStudent) {
                    $levelId = $sectionStudent->levelid ?? null;
                    if ($levelId) $requestor['grade_level'] = 'Grade ' . $levelId;
                    $section = DB::table('sections')->where('id', $sectionStudent->sectionid)->first();
                    if ($section) {
                        foreach (['sectionname','section_name','name','section'] as $col) {
                            if (isset($section->$col) && $section->$col !== null && $section->$col !== '') { $requestor['section'] = $section->$col; break; }
                        }
                    }
                }
            }

            // If no student found and this consultation is from an employee, try to load user + division
            if (empty($student) && $consult->requestor_type === 'employee' && !empty($consult->requestor_id)) {
                $u = DB::table('users')->where('id', $consult->requestor_id)->first();
                if ($u) {
                    $requestor['name'] = $u->name ?? $requestor['name'];
                    $requestor['sex'] = $u->sex ?? null;
                    // Determine division id column on users table
                    $divisionId = null;
                    foreach (['divisionid','division_id','division'] as $col) {
                        if (isset($u->$col) && $u->$col) { $divisionId = $u->$col; break; }
                    }
                    // Lookup division name from divisions table if available
                    if ($divisionId) {
                        $division = DB::table('divisions')->where('id', $divisionId)->first();
                        if ($division) {
                            // try common name columns
                            foreach (['name','division_name','division'] as $dn) {
                                if (isset($division->$dn) && $division->$dn) { $requestor['office'] = $division->$dn; break; }
                            }
                        }
                    }
                    // fallback: use user's office column if present
                    if (empty($requestor['office'])) {
                        foreach (['office','department','division','unit'] as $of) {
                            if (isset($u->$of) && $u->$of) { $requestor['office'] = $u->$of; break; }
                        }
                    }
                }
            }

            $consult->requestor_data = (object) $requestor;

            // Consolidate vital signs into an array/string for display
            $vitals = [];
            if (!empty($consult->temperature)) $vitals[] = 'Temp: ' . $consult->temperature;
            if (!empty($consult->pulse_rate)) $vitals[] = 'Pulse: ' . $consult->pulse_rate;
            if (!empty($consult->oxygen)) $vitals[] = 'SpO2: ' . $consult->oxygen;
            if (!empty($consult->blood_pressure)) $vitals[] = 'BP: ' . $consult->blood_pressure;
            $consult->vitals_display = !empty($vitals) ? implode(' ; ', $vitals) : null;

            // Action taken, medicine, time_out, disposition
            $consult->action_taken_display = $consult->action_taken ?? null;
            $consult->medicine_given_display = $consult->medicine_given ?? $consult->medicines ?? null;
            $consult->time_out_display = $consult->time_out ?? null;
            $consult->disposition_display = $consult->disposition ?? null;

            // Concern / reason
            $consult->concern_display = $consult->concern ?? $consult->reason ?? null;

            return $consult;
        });

        return view('consultations.logprint', ['consultations' => $consultations, 'type' => $type]);
    }

    /**
     * Delete a consultation. Only Administrator or Nurse may delete.
     */
    public function destroy(Request $request, Consultation $consultation)
    {
        $user = $request->user();

        if (! $user->hasAnyRole(['Administrator', 'Nurse'])) {
            abort(403);
        }

        try {
            $consultation->delete();
            return redirect()->route('consultations.index')->with('success', 'Consultation deleted');
        } catch (\Throwable $e) {
            logger()->error('Failed to delete consultation id='.$consultation->id.': '.$e->getMessage());
            return redirect()->route('consultations.index')->with('error', 'Failed to delete consultation');
        }
    }
}
