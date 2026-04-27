<?php

namespace App\Http\Controllers\AMS;

use App\Http\Controllers\Controller;
use App\Mail\AMS\ActivityEvaluationInviteMail;
use App\Mail\AMS\ActivityInvitationMail;
use App\Models\AMS\Activity;
use App\Models\AMS\ActivityCoProponent;
use App\Models\AMS\ActivityEvaluation;
use App\Models\AMS\ActivityParticipant;
use App\Models\AMS\ActivityStudentAttendance;
use App\Models\FacultyLoading\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\AMS\CertificateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ActivityController extends Controller
{
    public function __construct(private CertificateService $certService) {}

    // ── CRUD ─────────────────────────────────────────────────────────────────

    public function index()
    {
        $user  = Auth::user();
        $query = Activity::with('creator')->orderByDesc('start_date');

        if (!$user->isSuperAdmin() && !$user->hasPermission('activities.view_all')) {
            $coProponentIds = ActivityCoProponent::where('employee_id', $user->id)->pluck('activity_id');
            $query->where(function ($q) use ($user, $coProponentIds) {
                $q->where('user_id', $user->id)->orWhereIn('id', $coProponentIds);
            });
        }

        return Inertia::render('AMS/Index', [
            'activities' => $query->get()->map(fn($a) => $this->mapActivity($a)),
        ]);
    }

    public function create()
    {
        $this->requireManage();
        return Inertia::render('AMS/Form', [
            'activity'  => null,
            'employees' => $this->employeeList(),
        ]);
    }

    public function store(Request $request)
    {
        $this->requireManage();
        $data            = $this->validated($request);
        $data['user_id'] = Auth::id();
        $data            = array_merge($data, $this->handleUploads($request));
        Activity::create($data);

        return redirect()->route('ams.activities.index')->with('success', 'Activity created.');
    }

    public function show(Activity $activity)
    {
        $this->authorizeView($activity);
        $activity->load(['creator', 'coProponents.employee', 'participants', 'studentAttendance']);

        $sectionIds  = $activity->participants->where('participant_type', 'section')->pluck('participant_id');
        $employeeIds = $activity->participants->where('participant_type', 'employee')->pluck('participant_id');

        $sectionsMap  = Section::whereIn('id', $sectionIds)->get(['id', 'sectionname', 'levelid'])->keyBy('id');
        $employeesMap = User::whereIn('id', $employeeIds)->get(['id', 'name'])->keyBy('id');

        // Evaluation lookup: keyed by "type:participant_id"
        $evaluations = ActivityEvaluation::where('activity_id', $activity->id)
            ->get(['participant_type', 'participant_id'])
            ->keyBy(fn($e) => $e->participant_type . ':' . $e->participant_id);

        $participants = $activity->participants->map(function ($p) use ($sectionsMap, $employeesMap, $evaluations, $activity) {
            $evalHash      = md5($p->participant_id . '-' . $activity->id);
            $evaluateUrl   = route('ams.activities.evaluate.show', [$activity->id, $evalHash]);

            if ($p->participant_type === 'section') {
                $s = $sectionsMap[$p->participant_id] ?? null;
                return [
                    'id'             => $p->id,
                    'participant_id' => $p->participant_id,
                    'type'           => 'section',
                    'label'          => $s ? "Grade {$s->levelid} — {$s->sectionname}" : "Section #{$p->participant_id}",
                    'attended'       => $p->attended,
                    'hours_attended' => $p->hours_attended,
                    'evaluated'      => isset($evaluations['section:' . $p->participant_id]),
                    'evaluate_url'   => $evaluateUrl,
                ];
            }
            $u = $employeesMap[$p->participant_id] ?? null;
            return [
                'id'             => $p->id,
                'participant_id' => $p->participant_id,
                'type'           => 'employee',
                'label'          => $u?->name ?? "Employee #{$p->participant_id}",
                'attended'       => $p->attended,
                'hours_attended' => $p->hours_attended,
                'evaluated'      => isset($evaluations['employee:' . $p->participant_id]),
                'evaluate_url'   => $evaluateUrl,
            ];
        })->values()->all();

        [$canEdit, $canManage] = $this->abilities($activity);

        return Inertia::render('AMS/Show', [
            'activity'     => $this->mapActivity($activity),
            'participants' => $participants,
            'employees'    => $this->employeeList(),
            'sections'     => $this->sectionList(),
            'canEdit'      => $canEdit,
            'canManage'    => $canManage,
            'evaluations'  => $this->buildEvaluationSummary($activity),
        ]);
    }

    public function edit(Activity $activity)
    {
        $this->authorizeEdit($activity);
        return Inertia::render('AMS/Form', [
            'activity'  => $this->mapActivity($activity),
            'employees' => $this->employeeList(),
        ]);
    }

    public function update(Request $request, Activity $activity)
    {
        $this->authorizeEdit($activity);
        $data = array_merge($this->validated($request), $this->handleUploads($request, $activity));
        $activity->update($data);

        return redirect()->route('ams.activities.show', $activity)->with('success', 'Activity updated.');
    }

    public function destroy(Activity $activity)
    {
        $this->authorizeEdit($activity);
        foreach (['banner', 'special_order', 'activity_report', 'official_documentation'] as $f) {
            if ($activity->$f) Storage::disk('public')->delete($activity->$f);
        }
        $activity->delete();

        return redirect()->route('ams.activities.index')->with('success', 'Activity deleted.');
    }

    // ── Co-Proponents ─────────────────────────────────────────────────────────

    public function addCoProponent(Request $request, Activity $activity)
    {
        $this->authorizeManage($activity);
        $data = $request->validate(['employee_id' => 'required|exists:users,id']);
        $activity->coProponents()->firstOrCreate(['employee_id' => $data['employee_id']]);

        return back()->with('success', 'Co-proponent added.');
    }

    public function removeCoProponent(Activity $activity, ActivityCoProponent $coProponent)
    {
        $this->authorizeManage($activity);
        $coProponent->delete();

        return back()->with('success', 'Co-proponent removed.');
    }

    // ── Participants (bulk add + invite emails) ───────────────────────────────

    public function addParticipants(Request $request, Activity $activity)
    {
        $this->authorizeManage($activity);

        $data = $request->validate([
            'employee_ids'   => 'nullable|array',
            'employee_ids.*' => 'integer|exists:users,id',
            'section_ids'    => 'nullable|array',
            'section_ids.*'  => 'integer',
        ]);

        $added = 0;

        // Add employee participants
        foreach ($data['employee_ids'] ?? [] as $userId) {
            $participant = ActivityParticipant::firstOrCreate([
                'activity_id'      => $activity->id,
                'participant_id'   => $userId,
                'participant_type' => 'employee',
            ]);

            if ($participant->wasRecentlyCreated) {
                $added++;
                $user = User::find($userId);
                if ($user?->email) {
                    try {
                        Mail::to($user->email)->send(
                            new ActivityInvitationMail($activity, $user->name)
                        );
                    } catch (\Throwable $e) {
                        \Log::warning("AMS invite email failed for {$user->email}: " . $e->getMessage());
                    }
                }
            }
        }

        // Add section participants + seed student attendance rows
        foreach ($data['section_ids'] ?? [] as $sectionId) {
            $participant = ActivityParticipant::firstOrCreate([
                'activity_id'      => $activity->id,
                'participant_id'   => $sectionId,
                'participant_type' => 'section',
            ]);

            if ($participant->wasRecentlyCreated) {
                $added++;
                $studentIds = DB::table('section_students')
                    ->where('sectionid', $sectionId)
                    ->pluck('studentid');

                foreach ($studentIds as $studentId) {
                    ActivityStudentAttendance::firstOrCreate([
                        'activity_id'    => $activity->id,
                        'participant_id' => $studentId,
                    ]);
                }
            }
        }

        $msg = $added > 0
            ? "{$added} participant(s) added. Invitation emails sent."
            : 'All selected participants were already enrolled.';

        return back()->with('success', $msg);
    }

    /**
     * Bulk-send evaluation invite emails to all present employee participants.
     */
    public function sendEvaluationLinks(Activity $activity)
    {
        $this->authorizeManage($activity);

        $participants = ActivityParticipant::where('activity_id', $activity->id)
            ->where('participant_type', 'employee')
            ->where('attended', 'yes')
            ->get();

        $sent   = 0;
        $failed = 0;

        foreach ($participants as $p) {
            $user = User::find($p->participant_id);
            if (!$user?->email) { $failed++; continue; }

            $hash          = md5($p->participant_id . '-' . $activity->id);
            $evaluationUrl = route('ams.activities.evaluate.show', [$activity->id, $hash]);

            try {
                Mail::to($user->email)->send(
                    new ActivityEvaluationInviteMail($activity, $user->name, $evaluationUrl)
                );
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                \Log::warning("AMS: evaluation invite failed for {$user->email}: " . $e->getMessage());
            }
        }

        $msg = "Evaluation links sent to {$sent} participant(s).";
        if ($failed) $msg .= " {$failed} could not be sent (no email or delivery error).";

        return back()->with('success', $msg);
    }

    public function removeParticipant(Activity $activity, ActivityParticipant $participant)
    {
        $this->authorizeManage($activity);

        if ($participant->participant_type === 'section') {
            $studentIds = DB::table('section_students')
                ->where('sectionid', $participant->participant_id)
                ->pluck('studentid');

            ActivityStudentAttendance::where('activity_id', $activity->id)
                ->whereIn('participant_id', $studentIds)
                ->delete();
        }

        $participant->delete();

        return back()->with('success', 'Participant removed.');
    }

    /**
     * Save attendance + hours for a single employee participant.
     * If marking present, generate certificate and email it.
     */
    public function saveEmployeeAttendance(Request $request, Activity $activity, ActivityParticipant $participant)
    {
        $this->authorizeManage($activity);

        $data = $request->validate([
            'attended'       => 'required|in:yes,no',
            'hours_attended' => 'nullable|numeric|min:0|max:99.99',
        ]);

        $participant->update($data);

        if ($data['attended'] === 'yes') {
            $user = User::find($participant->participant_id);
            if ($user) {
                try {
                    $path = $this->certService->buildAndSave(
                        $activity,
                        $user->name,
                        $data['hours_attended'] ?? 0,
                        $participant->participant_id
                    );
                    $participant->update(['certificate_path' => $path]);

                    if ($user->email) {
                        $this->certService->sendCertificateEmail($activity, $user->email, $user->name, $path);
                    }
                } catch (\Throwable $e) {
                    \Log::warning("AMS: cert gen failed for user {$participant->participant_id}: " . $e->getMessage());
                }
            }
        }

        return back()->with('success', 'Attendance saved.');
    }

    // ── Student Attendance ────────────────────────────────────────────────────

    public function sectionStudents(Activity $activity, ActivityParticipant $participant)
    {
        $this->authorizeView($activity);

        $studentIds = DB::table('section_students')
            ->where('sectionid', $participant->participant_id)
            ->pluck('studentid');

        $attendance = ActivityStudentAttendance::where('activity_id', $activity->id)
            ->whereIn('participant_id', $studentIds)
            ->get()
            ->keyBy('participant_id');

        $students = Student::whereIn('id', $studentIds)
            ->orderBy('lastname')
            ->get(['id', 'firstname', 'lastname', 'middlename', 'student_email']);

        return response()->json(
            $students->map(fn($s) => [
                'id'             => $s->id,
                'name'           => $s->full_name,
                'attended'       => $attendance[$s->id]?->attended ?? 'no',
                'hours_attended' => $attendance[$s->id]?->hours_attended ?? '0.00',
                'attendance_id'  => $attendance[$s->id]?->id,
            ])->values()
        );
    }

    /**
     * Bulk save student attendance for a section.
     * Generates + emails certificates for all present students.
     */
    public function saveSectionAttendance(Request $request, Activity $activity, ActivityParticipant $participant)
    {
        $this->authorizeManage($activity);

        $data = $request->validate([
            'students'                 => 'required|array',
            'students.*.attendance_id' => 'required|integer',
            'students.*.attended'      => 'required|in:yes,no',
            'students.*.hours_attended'=> 'nullable|numeric|min:0|max:999.99',
            'students.*.student_id'    => 'required|integer',
        ]);

        $studentMap = Student::whereIn('id', collect($data['students'])->pluck('student_id'))
            ->get(['id', 'firstname', 'lastname', 'middlename', 'student_email'])
            ->keyBy('id');

        $errors = 0;

        foreach ($data['students'] as $row) {
            $attendance = ActivityStudentAttendance::find($row['attendance_id']);
            if (!$attendance) continue;

            $attendance->update([
                'attended'       => $row['attended'],
                'hours_attended' => $row['hours_attended'] ?? 0,
            ]);

            if ($row['attended'] === 'yes') {
                $student = $studentMap[$row['student_id']] ?? null;
                if ($student) {
                    try {
                        $path = $this->certService->buildAndSave(
                            $activity,
                            $student->full_name,
                            $row['hours_attended'] ?? 0,
                            $student->id
                        );
                        $attendance->update(['certificate_path' => $path]);

                        if (!empty($student->student_email)) {
                            $this->certService->sendCertificateEmail(
                                $activity, $student->student_email, $student->full_name, $path
                            );
                        }
                    } catch (\Throwable $e) {
                        $errors++;
                        \Log::warning("AMS: cert gen failed for student {$student->id}: " . $e->getMessage());
                    }
                }
            }
        }

        $msg = $errors > 0
            ? 'Attendance saved. Some certificates could not be generated (check logs).'
            : 'Attendance saved and certificates sent to present students.';

        return back()->with('success', $msg);
    }

    // ── Authorization ─────────────────────────────────────────────────────────

    private function abilities(Activity $activity): array
    {
        $user = Auth::user();
        if ($user->isSuperAdmin()) return [true, true];

        $isOwner       = $activity->user_id === $user->id;
        $isCoProponent = $activity->relationLoaded('coProponents')
            ? $activity->coProponents->contains('employee_id', $user->id)
            : ActivityCoProponent::where('activity_id', $activity->id)->where('employee_id', $user->id)->exists();

        return [$isOwner, $isOwner || $isCoProponent];
    }

    private function authorizeView(Activity $activity): void
    {
        $user = Auth::user();
        if ($user->isSuperAdmin() || $user->hasPermission('activities.view_all')) return;

        $isOwner = $activity->user_id === $user->id;
        $isCo    = ActivityCoProponent::where('activity_id', $activity->id)->where('employee_id', $user->id)->exists();
        if (!$isOwner && !$isCo) abort(403);
    }

    private function authorizeEdit(Activity $activity): void
    {
        $user = Auth::user();
        if ($user->isSuperAdmin()) return;
        if ($activity->user_id !== $user->id) abort(403);
    }

    private function authorizeManage(Activity $activity): void
    {
        $user = Auth::user();
        if ($user->isSuperAdmin()) return;

        $isOwner = $activity->user_id === $user->id;
        $isCo    = ActivityCoProponent::where('activity_id', $activity->id)->where('employee_id', $user->id)->exists();
        if (!$isOwner && !$isCo) abort(403);
    }

    private function requireManage(): void
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin() && !$user->hasPermission('activities.manage')) abort(403);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function validated(Request $request): array
    {
        return $request->validate([
            'title'           => 'required|string|max:255',
            'start_date'      => 'required|date',
            'start_time'      => 'nullable|date_format:H:i',
            'end_date'        => 'required|date|after_or_equal:start_date',
            'end_time'        => 'nullable|date_format:H:i',
            'total_hours'     => 'nullable|string|max:25',
            'venue'           => 'nullable|string|max:255',
            'resource_person' => 'nullable|string|max:255',
            'banner'          => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'special_order'   => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'activity_report' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'official_documentation' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);
    }

    private function handleUploads(Request $request, ?Activity $existing = null): array
    {
        $paths = [];
        foreach (['banner', 'special_order', 'activity_report', 'official_documentation'] as $field) {
            if ($request->hasFile($field)) {
                if ($existing && $existing->$field) Storage::disk('public')->delete($existing->$field);
                $paths[$field] = $request->file($field)->store("ams/{$field}s", 'public');
            }
        }
        return $paths;
    }

    private function mapActivity(Activity $a): array
    {
        return [
            'id'                     => $a->id,
            'title'                  => $a->title,
            'start_date'             => $a->start_date?->toDateString(),
            'start_time'             => $a->start_time,
            'end_date'               => $a->end_date?->toDateString(),
            'end_time'               => $a->end_time,
            'total_hours'            => $a->total_hours,
            'venue'                  => $a->venue,
            'resource_person'        => $a->resource_person,
            'banner'                 => $a->banner ? Storage::disk('public')->url($a->banner) : null,
            'special_order'          => $a->special_order ? Storage::disk('public')->url($a->special_order) : null,
            'activity_report'        => $a->activity_report ? Storage::disk('public')->url($a->activity_report) : null,
            'official_documentation' => $a->official_documentation ? Storage::disk('public')->url($a->official_documentation) : null,
            'creator'                => $a->relationLoaded('creator') && $a->creator
                ? ['id' => $a->creator->id, 'name' => $a->creator->name]
                : null,
            'co_proponents'          => $a->relationLoaded('coProponents')
                ? $a->coProponents->map(fn($cp) => [
                    'id'          => $cp->id,
                    'employee_id' => $cp->employee_id,
                    'name'        => $cp->employee?->name,
                ])->values()->all()
                : [],
        ];
    }

    private function buildEvaluationSummary(Activity $activity): array
    {
        $rows = ActivityEvaluation::where('activity_id', $activity->id)
            ->orderByDesc('created_at')
            ->get();

        if ($rows->isEmpty()) {
            return ['count' => 0, 'sections' => [], 'responses' => []];
        }

        $weights = ActivityEvaluation::WEIGHTS;

        $sections = [
            'A' => [
                'label'     => 'Objectives',
                'questions' => [
                    'obj_1' => 'The activity was aligned with the school\'s vision and mission.',
                    'obj_2' => 'The objectives were relevant to the needs of the participants.',
                    'obj_3' => 'The activity contributed to the academic, personal, or social development of the participants.',
                    'obj_4' => 'The objectives were achieved.',
                ],
            ],
            'B' => [
                'label'     => 'Management',
                'questions' => [
                    'mgmt_1' => 'Organizers and staff were visible and responsive.',
                    'mgmt_2' => 'Coordination and flow of the program were clear.',
                    'mgmt_3' => 'Time was managed effectively.',
                    'mgmt_4' => 'Transitions between parts of the activity were smooth.',
                    'mgmt_5' => 'The activity was well-organized.',
                    'mgmt_6' => 'Participants were actively engaged.',
                ],
            ],
            'C' => [
                'label'     => 'Physical Arrangements',
                'questions' => [
                    'phys_1' => 'Venue and materials were ready and adequate.',
                    'phys_2' => 'The sound system and equipment functioned properly.',
                    'phys_3' => 'Participants were properly guided within the venue.',
                ],
            ],
        ];

        $allOptions = ['strongly_agree', 'agree', 'neutral', 'disagree', 'strongly_disagree', 'not_applicable'];

        $builtSections = [];
        foreach ($sections as $key => $section) {
            $questions = [];
            $sectionScores = [];

            foreach ($section['questions'] as $field => $label) {
                $dist = array_fill_keys($allOptions, 0);
                $scores = [];

                foreach ($rows as $row) {
                    $val = $row->$field;
                    if ($val) $dist[$val] = ($dist[$val] ?? 0) + 1;
                    $w = $weights[$val] ?? null;
                    if ($w !== null) $scores[] = $w;
                }

                $questions[] = [
                    'field' => $field,
                    'label' => $label,
                    'avg'   => count($scores) ? round(array_sum($scores) / count($scores), 2) : null,
                    'dist'  => $dist,
                    'count' => count($scores),
                ];

                array_push($sectionScores, ...$scores);
            }

            $builtSections[] = [
                'key'       => $key,
                'label'     => $section['label'],
                'avg'       => count($sectionScores) ? round(array_sum($sectionScores) / count($sectionScores), 2) : null,
                'questions' => $questions,
            ];
        }

        $responses = $rows->map(fn($r) => [
            'id'             => $r->id,
            'participant_type' => $r->participant_type,
            'evaluator_name' => $r->evaluator_name ?? 'Anonymous',
            'suggestions'    => $r->suggestions,
            'other_comments' => $r->other_comments,
            'submitted_at'   => $r->created_at->toDateTimeString(),
        ])->values()->all();

        return [
            'count'     => $rows->count(),
            'sections'  => $builtSections,
            'responses' => $responses,
        ];
    }

    private function employeeList(): array
    {
        return User::where('status', '<>', 'inactive')->orderBy('name')->get(['id', 'name'])->toArray();
    }

    private function sectionList(): array
    {
        return Section::where('is_active', true)
            ->orderBy('levelid')->orderBy('sectionname')
            ->get(['id', 'sectionname', 'levelid'])
            ->map(fn($s) => ['id' => $s->id, 'label' => "Grade {$s->levelid} — {$s->sectionname}"])
            ->toArray();
    }
}
