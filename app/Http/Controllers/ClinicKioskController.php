<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PhysicianSchedule;
use App\Models\Consultation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Mail;
use App\Mail\ClinicKioskConsultationMail;
use App\Models\User;
use Carbon\Carbon;

class ClinicKioskController extends Controller
{
    public function index(Request $request)
    {
        $today = Carbon::now('Asia/Manila')->toDateString();

        // Doctor schedules were removed; provide empty schedules list for compatibility with the view.
        $schedules = [];

        $physicianSchedules = PhysicianSchedule::whereDate('schedule_date', '>=', $today)
            ->orderBy('schedule_date')
            ->orderBy('time_start')
            ->get();

        return Inertia::render('Clinic/Kiosk', [
            'schedules' => $schedules,
            'physicianSchedules' => $physicianSchedules,
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            'pisay' => 'nullable|string|max:255',
            'consultation_type' => 'required|in:walk-in,scheduled',
            'concern' => 'required|string|max:2000',
        ];

        if ($request->input('consultation_type') === 'scheduled') {
            // only physician schedules are used now
            $rules['physician_schedule_id'] = 'nullable|integer|exists:physician_schedule,id';
        }

        $data = $request->validate($rules);

        // Transactional create and availability check
        DB::beginTransaction();
        try {
            // Avoid logging full request payloads which may contain PII
            Log::info('ClinicKiosk store', ['user_id' => optional(auth()->user())->id, 'keys' => array_keys($request->all())]);
            // doctor schedule availability checks removed since doctor schedules were dropped

            // resolve student id from pisay if provided
            $studentId = null;
            if (!empty($data['pisay'])) {
                $cols = collect(DB::select("SHOW COLUMNS FROM students"))->map(fn($c) => $c->Field)->all();
                $pisayCandidates = ['pisaysystemid','pisaysystemID','pisaysystem_id','pisay_system_id','pisay_id','pisayid'];
                $pisayCol = null;
                foreach ($pisayCandidates as $c) {
                    if (in_array($c, $cols)) { $pisayCol = $c; break; }
                }

                // try exact match first, then try cleaned (remove non-alnum) match
                $student = null;
                if ($pisayCol) {
                    $student = DB::table('students')->where($pisayCol, $data['pisay'])->first();
                    if (! $student) {
                        $clean = preg_replace('/[^A-Za-z0-9]/', '', $data['pisay']);
                        $student = DB::table('students')->where($pisayCol, $clean)->first();
                    }
                } else {
                    $clean = preg_replace('/[^A-Za-z0-9]/', '', $data['pisay']);
                    $student = DB::table('students')->whereRaw('LOWER(CONCAT_WS(" ", '.implode(', ', array_map(fn($c)=>"`$c`", $cols)).')) LIKE ?', ["%{$data['pisay']}%"])->first();
                    if (! $student) {
                        $student = DB::table('students')->whereRaw('LOWER(CONCAT_WS(" ", '.implode(', ', array_map(fn($c)=>"`$c`", $cols)).')) LIKE ?', ["%{$clean}%"])->first();
                    }
                }

                $studentId = $student?->id ?? null;

                // if user provided a PISAY but no student matched, return validation error
                if (empty($studentId)) {
                    DB::rollBack();
                    throw ValidationException::withMessages(['pisay' => ['PISAY ID not found']]);
                }
            }

            // Create a Consultation record for both walk-in and scheduled
            $consultationPayload = [
                'requestor_id' => $studentId,
                'requestor_type' => 'student',
                'reason' => $data['concern'],
                'consultation_type' => $data['consultation_type'],
                'status' => $data['consultation_type'] === 'walk-in' ? 'active' : 'pending',
                'schedule_id' => $data['physician_schedule_id'] ?? null,
            ];

            // If a schedule id was provided, attempt to populate scheduled_at from that schedule
            $scheduledAt = null;
            if (!empty($data['physician_schedule_id'])) {
                $ps = PhysicianSchedule::find($data['physician_schedule_id']);
                if ($ps) {
                    $scheduledAt = Carbon::parse($ps->schedule_date . ' ' . ($ps->time_start ?? '00:00:00'));
                }
            }

            if ($scheduledAt) {
                $consultationPayload['scheduled_at'] = $scheduledAt->toDateTimeString();
            }

            Log::info('Creating consultation', $consultationPayload);

            $consult = Consultation::create($consultationPayload);

            // Notify nurses using the standard notification Blade so formatting matches other emails
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
                            $requestor['name'] = $last && $first ? ($last . ', ' . $first) : implode(' ', $nameParts);
                        } else {
                            $requestor['name'] = $student->name ?? $requestor['name'];
                        }

                        $requestor['sex'] = $student->sex ?? null;

                        $sectionStudent = DB::table('section_students')->where('studentid', $student->id)->orderByDesc('id')->first();
                        if ($sectionStudent) {
                            $levelId = $sectionStudent->levelid ?? null;
                            if ($levelId) {
                                $requestor['grade_level'] = 'Grade ' . $levelId;
                            }
                            $section = DB::table('sections')->where('id', $sectionStudent->sectionid)->first();
                            if ($section) {
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
                        $user = DB::table('users')->where('id', $consult->requestor_id)->first();
                        if ($user) {
                            $requestor['name'] = $user->name ?? $requestor['name'];
                            $requestor['sex'] = $user->sex ?? null;
                            $requestor['office'] = $user->office ?? null;
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
                        Log::info('Sent clinic consultation email to: '.$email);
                    } catch (\Throwable $e) {
                        Log::error('Failed to send clinic consultation email to '.$email.': '.$e->getMessage());
                        report($e);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Failed to prepare/send clinic consultation emails: '.$e->getMessage());
                report($e);
            }

            DB::commit();
            return redirect()->route('clinic.kiosk')->with('success', 'Clinic consultation logged.');
        } catch (\Exception $e) {
            DB::rollBack();
            if ($e instanceof ValidationException) {
                throw $e;
            }
            Log::error('ClinicKiosk store failed', ['exception' => $e->getMessage()]);
            return back()->with('error', 'Failed to log visit.');
        }
    }
}
