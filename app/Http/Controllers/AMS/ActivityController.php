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
use App\Exports\AMS\ActivityEvaluationExport;
use App\Services\AMS\ActivityEvaluationExportService;
use App\Services\AMS\ActivityEvaluationSummaryService;
use App\Services\AMS\ActivityFileService;
use App\Services\AMS\CertificateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ActivityController extends Controller
{
    private const FILE_FIELDS = ['banner', 'special_order', 'activity_report', 'official_documentation'];

    public function __construct(
        private CertificateService $certService,
        private ActivityFileService $fileService,
        private ActivityEvaluationSummaryService $evalSummaryService,
        private ActivityEvaluationExportService $evalExportService,
    ) {}

    // ── CRUD ─────────────────────────────────────────────────────────────────

    public function index()
    {
        $user  = Auth::user();
        $query = Activity::with('creator')->orderByDesc('start_date');

        if (!$user->isSuperAdmin() && !$user->hasAnyPermission(['activities.view_all', 'activities.monitor'])) {
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
        $data      = $this->validated($request);
        $mealPlans = $data['meal_plans'] ?? [];
        $speakers  = $data['speakers'] ?? [];
        unset($data['meal_plans'], $data['speakers']);
        foreach (self::FILE_FIELDS as $field) {
            unset($data[$field]);
        }
        $data['user_id'] = Auth::id();

        $activity = Activity::create($data);

        $uploads = $this->handleUploads($request, $activity);
        if ($uploads) {
            $activity->update($uploads);
        }

        $this->syncMealPlans($activity, $mealPlans);
        $this->syncSpeakers($activity, $speakers);

        return redirect()->route('ams.activities.index')->with('success', 'Activity created.');
    }

    public function show(Activity $activity)
    {
        $this->authorizeView($activity);
        $activity->load(['creator', 'coProponents.employee', 'participants', 'studentAttendance', 'mealPlans', 'speakers']);

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
            'quizzes'      => \App\Models\Quiz\Quiz::where('source_type', 'activity')
                ->where('source_id', $activity->id)
                ->withCount('questions')
                ->get()
                ->map(fn ($q) => ['id' => $q->id, 'title' => $q->title, 'status' => $q->status, 'question_count' => $q->questions_count]),
        ]);
    }

    public function exportEvaluations(Activity $activity)
    {
        $this->authorizeView($activity);

        $data     = $this->evalExportService->perActivityData($activity);
        $filename = Str::slug($activity->title) . '-evaluations-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new ActivityEvaluationExport($activity, $data), $filename);
    }

    public function edit(Activity $activity)
    {
        $this->authorizeEdit($activity);
        $activity->load(['mealPlans', 'speakers']);
        return Inertia::render('AMS/Form', [
            'activity'  => $this->mapActivity($activity),
            'employees' => $this->employeeList(),
        ]);
    }

    public function update(Request $request, Activity $activity)
    {
        $this->authorizeEdit($activity);
        $data      = $this->validated($request);
        $mealPlans = $data['meal_plans'] ?? [];
        $speakers  = $data['speakers'] ?? [];
        unset($data['meal_plans'], $data['speakers']);
        foreach (self::FILE_FIELDS as $field) {
            unset($data[$field]);
        }

        $uploads = $this->handleUploads($request, $activity, $activity);
        $activity->update(array_merge($data, $uploads));

        $this->syncMealPlans($activity, $mealPlans);
        $this->syncSpeakers($activity, $speakers);

        return redirect()->route('ams.activities.show', $activity)->with('success', 'Activity updated.');
    }

    public function destroy(Activity $activity)
    {
        $this->authorizeEdit($activity);
        foreach (self::FILE_FIELDS as $f) {
            $this->fileService->delete($activity->$f);
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
                        report($e);
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
                report($e);
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
        if ($user->isSuperAdmin() || $user->hasAnyPermission(['activities.view_all', 'activities.monitor'])) return;

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
            'activity_type'   => 'required|in:in_house,training_workshop_seminar',
            'start_date'      => 'required|date',
            'start_time'      => 'nullable|date_format:H:i',
            'end_date'        => 'required|date|after_or_equal:start_date',
            'end_time'        => 'nullable|date_format:H:i',
            'total_hours'     => 'nullable|string|max:25',
            'venue'           => 'nullable|string|max:255',
            'resource_person' => 'nullable|string|max:255',
            'what_to_bring'   => 'nullable|string|max:5000',

            'meal_plans'             => 'nullable|array',
            'meal_plans.*.date'      => 'required|date',
            'meal_plans.*.am_snacks' => 'nullable|boolean',
            'meal_plans.*.lunch'     => 'nullable|boolean',
            'meal_plans.*.pm_snacks' => 'nullable|boolean',
            'meal_plans.*.dinner'    => 'nullable|boolean',

            'speakers'               => 'required_if:activity_type,training_workshop_seminar|array|min:1',
            'speakers.*.id'          => 'nullable|integer',
            'speakers.*.name'        => 'required|string|max:255',
            'speakers.*.designation' => 'nullable|string|max:255',
            'speakers.*.topic'       => 'nullable|string|max:255',

            // base64 data URIs — Cloudflare WAF blocks multipart/form-data uploads
            'banner'                 => 'nullable|string|max:7000000',
            'special_order'          => 'nullable|string|max:7000000',
            'activity_report'        => 'nullable|string|max:14000000',
            'official_documentation' => 'nullable|string|max:14000000',
        ]);
    }

    private function syncMealPlans(Activity $activity, array $mealPlans): void
    {
        $activity->mealPlans()->delete();

        foreach ($mealPlans as $mp) {
            if (empty($mp['am_snacks']) && empty($mp['lunch']) && empty($mp['pm_snacks']) && empty($mp['dinner'])) {
                continue;
            }
            $activity->mealPlans()->create([
                'date'      => $mp['date'],
                'am_snacks' => (bool) ($mp['am_snacks'] ?? false),
                'lunch'     => (bool) ($mp['lunch'] ?? false),
                'pm_snacks' => (bool) ($mp['pm_snacks'] ?? false),
                'dinner'    => (bool) ($mp['dinner'] ?? false),
            ]);
        }
    }

    /**
     * Updates/creates speakers by id where possible and only removes ones no
     * longer present, so existing speaker evaluations aren't wiped out by an
     * unrelated edit to the speaker list (removing a speaker cascades their
     * evaluations, which is intentional).
     */
    private function syncSpeakers(Activity $activity, array $speakers): void
    {
        $keepIds = [];

        foreach ($speakers as $i => $sp) {
            if (empty($sp['name'])) continue;

            $attrs = [
                'name'        => $sp['name'],
                'designation' => $sp['designation'] ?? null,
                'topic'       => $sp['topic'] ?? null,
                'sort_order'  => $i,
            ];

            $speaker = !empty($sp['id']) ? $activity->speakers()->find($sp['id']) : null;
            if ($speaker) {
                $speaker->update($attrs);
            } else {
                $speaker = $activity->speakers()->create($attrs);
            }
            $keepIds[] = $speaker->id;
        }

        $activity->speakers()->whereNotIn('id', $keepIds ?: [0])->delete();
    }

    private function handleUploads(Request $request, Activity $activity, ?Activity $existing = null): array
    {
        $paths = [];
        foreach (self::FILE_FIELDS as $field) {
            $dataUri = $request->input($field);
            if ($dataUri && str_starts_with($dataUri, 'data:')) {
                if ($existing && $existing->$field) {
                    $this->fileService->delete($existing->$field);
                }
                $paths[$field] = $this->fileService->uploadBase64File($activity->id, $field, $dataUri);
            }
        }
        return $paths;
    }

    private function resolveFileUrl(?string $stored, Activity $activity, string $field): ?string
    {
        if (!$stored) return null;
        if (str_starts_with($stored, 's3.')) {
            return route('ams.activities.file', [$activity->id, $field]);
        }
        // Legacy pre-refactor uploads still on Storage::disk('public')
        return Storage::disk('public')->url($stored);
    }

    private function mapActivity(Activity $a): array
    {
        return [
            'id'                     => $a->id,
            'title'                  => $a->title,
            'activity_type'          => $a->activity_type,
            'start_date'             => $a->start_date?->toDateString(),
            'start_time'             => $a->start_time,
            'end_date'               => $a->end_date?->toDateString(),
            'end_time'               => $a->end_time,
            'total_hours'            => $a->total_hours,
            'venue'                  => $a->venue,
            'resource_person'        => $a->resource_person,
            'what_to_bring'          => $a->what_to_bring,
            'banner'                 => $this->resolveFileUrl($a->banner, $a, 'banner'),
            'special_order'          => $this->resolveFileUrl($a->special_order, $a, 'special_order'),
            'activity_report'        => $this->resolveFileUrl($a->activity_report, $a, 'activity_report'),
            'official_documentation' => $this->resolveFileUrl($a->official_documentation, $a, 'official_documentation'),
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
            'meal_plans'             => $a->relationLoaded('mealPlans')
                ? $a->mealPlans->map(fn($mp) => [
                    'id'        => $mp->id,
                    'date'      => $mp->date->toDateString(),
                    'am_snacks' => $mp->am_snacks,
                    'lunch'     => $mp->lunch,
                    'pm_snacks' => $mp->pm_snacks,
                    'dinner'    => $mp->dinner,
                ])->values()->all()
                : [],
            'speakers'               => $a->relationLoaded('speakers')
                ? $a->speakers->map(fn($s) => [
                    'id'          => $s->id,
                    'name'        => $s->name,
                    'designation' => $s->designation,
                    'topic'       => $s->topic,
                ])->values()->all()
                : [],
        ];
    }

    private function buildEvaluationSummary(Activity $activity): array
    {
        return $this->evalSummaryService->build($activity);
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
