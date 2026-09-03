<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordAssessmentDeletionRequest;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordScore;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use App\Services\ClassRecord\AssessmentPlottingService;
use App\Services\ClassRecord\ClassRecordMonitorScopeService;
use App\Services\ClassRecord\WatRuleService;
use App\Services\NotificationService;
use App\Services\PersonNameFormatter;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassRecordAssessmentController extends Controller
{
    public function __construct(
        private readonly ClassRecordMonitorScopeService $monitorScope,
        private readonly AssessmentPlottingService $plottingService,
    ) {}

    private function isAdmin(): bool
    {
        return Auth::user()->hasPermission('class-records.admin');
    }

    /** Read-only access: admin, the owning teacher, or a scoped monitor (CID Chief / AUH). */
    private function canView(ClassRecord $classRecord): bool
    {
        return $classRecord->canView(Auth::user())
            || $this->monitorScope->canView(Auth::user(), $classRecord);
    }

    private function resolveQuarter(ClassRecord $classRecord, int $q): ClassRecordQuarter
    {
        abort_unless(in_array($q, [1, 2, 3, 4]), 422, 'Quarter must be 1-4.');

        return ClassRecordQuarter::firstOrCreate(
            ['class_record_id' => $classRecord->id, 'quarter' => $q],
            ['is_locked' => false]
        );
    }

    // ── GET /class-records/{cr}/quarters/{q}/assessments ─────────────────────

    public function index(ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($this->canView($classRecord), 403);

        $quarter = $this->resolveQuarter($classRecord, $q);
        $assessments = ClassRecordAssessment::with(['gradingCategory', 'dates'])
            ->where('class_record_quarter_id', $quarter->id)
            ->orderByRaw('CASE WHEN activity_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('activity_date')
            ->orderBy('assessment_number')
            ->orderBy('id')
            ->get();
        $assessments->each(fn ($assessment) => $assessment->setAttribute(
            'activity_dates',
            $assessment->activityDateStrings()->all()
        ));

        return response()->json($assessments);
    }

    // ── GET /class-records/{cr}/section-calendar ──────────────────────────────

    public function sectionCalendar(ClassRecord $classRecord): JsonResponse
    {
        abort_unless($this->canView($classRecord), 403);
        abort_if(! $classRecord->section_id, 422, 'This class record has no section linked.');

        // Pool in any Science Core/Elective synthetic sections at this
        // section's grade — same shared daily/weekly budget the backend cap
        // check (ClassRecordAssessmentController::upsert()) and WatRuleService
        // enforce. Without this, the calendar under-counts what actually
        // blocks a new date pick when the budget is being consumed by a
        // pooled elective class, not just this homeroom's own records.
        $grade = $classRecord->section?->levelid;
        $poolSectionIds = $grade !== null
            ? WatRuleService::poolSectionIds($classRecord->section_id, (int) $grade)
            : [$classRecord->section_id];

        // Routed through the shared schoolYearScopeQuery() so archived class
        // records are excluded the same way everywhere else in the WAT —
        // a hand-rolled join here previously counted archived records'
        // assessments toward the daily cap, falsely blocking new dates.
        $rows = WatRuleService::assessmentOccurrencesQuery($classRecord->school_year_id)
            ->whereIn('cr.section_id', $poolSectionIds)
            ->join('grading_categories as gc', 'class_record_assessments.grading_category_id', '=', 'gc.id')
            ->orderByRaw(WatRuleService::OCCURRENCE_DATE_SQL)
            ->select([
                'class_record_assessments.id',
                'class_record_assessments.title',
                'class_record_assessments.assessment_type',
                'class_record_assessments.is_graded',
                'class_record_assessments.is_major',
                'crad.id as assessment_date_id',
                'cr.id as class_record_id',
                'cr.subject_name',
                'cr.teacher_id',
                'gc.code as category_code',
            ])
            ->selectRaw(WatRuleService::OCCURRENCE_DATE_SQL.' as activity_date')
            ->get();

        $rows->groupBy('id')->each(function ($occurrences) {
            $ordered = $occurrences->sortBy('activity_date')->values();
            $total = $ordered->count();
            $ordered->each(function ($occurrence, $index) use ($total) {
                $occurrence->occurrence_number = $index + 1;
                $occurrence->occurrence_total = $total;
            });
        });

        $teacherIds = $rows->pluck('teacher_id')->filter()->unique()->values()->toArray();
        $teachers = User::whereIn('id', $teacherIds)
            ->get(['id', 'name', 'prenominal_title', 'postnominal_title'])
            ->keyBy('id');
        $nameFormatter = new PersonNameFormatter;

        $days = $rows->groupBy(fn ($row) => $row->activity_date instanceof Carbon
                ? $row->activity_date->toDateString()
                : (string) $row->activity_date)
            ->map(function ($items, $date) use ($classRecord, $grade, $teachers, $nameFormatter) {
                $counts = $grade !== null
                    ? WatRuleService::gradeCountsOnDate(
                        $classRecord->section_id,
                        (int) $grade,
                        $classRecord->school_year_id,
                        $date
                    )
                    : [
                        'graded' => $items->where('is_graded', true)->count(),
                        'major' => $items->where('is_graded', true)->where('is_major', true)->count(),
                    ];

                return [
                    'date' => $date,
                    'count' => $items->count(),
                    'graded_count' => $counts['graded'],
                    'major_count' => $counts['major'],
                    'items' => $items->map(fn ($row) => [
                        'id' => $row->id,
                        'occurrence_id' => $row->assessment_date_id
                            ? "{$row->id}:{$row->assessment_date_id}"
                            : (string) $row->id,
                        'occurrence_number' => $row->occurrence_number,
                        'occurrence_total' => $row->occurrence_total,
                        'title' => $row->title,
                        'subject_name' => $row->subject_name,
                        'teacher_name' => $teachers[$row->teacher_id]
                            ? $nameFormatter->withTitles($teachers[$row->teacher_id], $teachers[$row->teacher_id]->name ?? '')
                            : null,
                        'category_code' => $row->category_code,
                        'assessment_type' => $row->assessment_type,
                        'is_graded' => (bool) $row->is_graded,
                        'is_major' => (bool) $row->is_major,
                        'is_own_record' => $row->class_record_id === $classRecord->id,
                    ])->values(),
                ];
            })
            ->values();

        return response()->json($days);
    }

    // ── POST /class-records/{cr}/quarters/{q}/assessments ────────────────────

    public function upsert(Request $request, ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($classRecord->canEdit(Auth::user()), 403);

        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');
        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_if($quarter->is_locked, 403, 'Quarter is locked. Unlock it before editing assessments.');

        $validated = $request->validate([
            'assessments' => 'required|array|min:1',
            'assessments.*.id' => 'nullable|integer',
            'assessments.*.grading_category_id' => 'required|integer|exists:grading_categories,id',
            'assessments.*.assessment_number' => 'required|integer|min:1',
            'assessments.*.title' => 'required|string|max:255',
            'assessments.*.is_graded' => 'sometimes|boolean',
            'assessments.*.activity_date' => 'required|date',
            'assessments.*.activity_dates' => 'sometimes|array|min:1',
            'assessments.*.activity_dates.*' => 'required|date',
            'assessments.*.max_score' => 'nullable|numeric|min:0',
            'assessments.*.sort_order' => 'sometimes|integer|min:0',
            'confirm_non_graded_score_removal' => 'sometimes|boolean',
        ]);

        // Assessments may only attach to LEAF categories of the grading option
        // in force for THIS quarter (per-quarter override, else record default).
        $optionId = $quarter->grading_option_id ?? $classRecord->grading_option_id;
        $option = GradingOption::with('categories')->find($optionId);
        $leafIds = $option ? $option->leafCategories()->pluck('id')->map(fn ($id) => (int) $id)->all() : [];
        // Every leaf here belongs to $option itself (leafIds is derived from
        // it) — hydrate the gradingOption relation from what's already
        // loaded instead of an extra query per category (isMajor() below
        // needs it to detect compliance-mode grading).
        $categories = GradingCategory::whereIn('id', $leafIds)->get()->keyBy('id')
            ->each(fn ($category) => $category->setRelation('gradingOption', $option));
        $isAdminUser = $this->isAdmin();

        foreach ($validated['assessments'] as $item) {
            abort_unless(
                in_array((int) $item['grading_category_id'], $leafIds, true),
                422,
                'One or more assessments reference a category that is not part of this quarter\'s grading option.',
            );

            // Laravel's `distinct` rule on a nested wildcard (activity_dates.*)
            // checks uniqueness across EVERY assessment's dates in the whole
            // payload, not just this one's own list — which incorrectly
            // rejects the normal case of two different assessments sharing a
            // date. Check for a genuine internal repeat within this single
            // assessment's own dates instead, scoped and named so the error
            // is actually actionable.
            $dates = $item['activity_dates'] ?? [$item['activity_date']];
            if (count($dates) !== count(array_unique($dates))) {
                return response()->json([
                    'message' => "\"{$item['title']}\" lists the same date more than once — remove the duplicate before saving.",
                ], 422);
            }
        }

        // On a shared (e.g. PEHM) record, a leaf tagged with a subject_id may
        // only be written to by that subject's teacher — this keeps the PE
        // teacher from editing Music's assessments and vice versa. Leaves
        // with no subject_id (the normal case) stay scoped to any of the
        // record's teachers, same as canEdit(null) above.
        //
        // The Setup tab always resubmits every leaf's rows on every save —
        // a co-teacher's payload therefore includes their own edits AND every
        // sibling subject's already-saved rows, unchanged. Aborting the whole
        // request the moment one of those foreign rows shows up would mean no
        // co-teacher could ever save once a sibling subject has anything
        // plotted (the normal case). So instead: silently drop rows outside
        // the requester's own subject(s) from the write-set — they're a
        // no-op either way — rather than rejecting the batch.
        $isCoTeacherScoped = ! $isAdminUser && $classRecord->coTeachers()->exists();
        $editableCategoryIds = $isCoTeacherScoped
            ? $categories->filter(fn ($category) => $classRecord->canEdit(Auth::user(), $category->subject_id))->keys()->all()
            : $leafIds;

        $assessmentsInput = collect($validated['assessments']);
        if ($isCoTeacherScoped) {
            $assessmentsInput = $assessmentsInput
                ->filter(fn ($item) => in_array((int) $item['grading_category_id'], $editableCategoryIds, true))
                ->values();
            abort_if($assessmentsInput->isEmpty(), 403, 'You do not have edit access to any of the categories in this save.');
        }

        // Existing rows are matched by their stable primary key, not by
        // (category, number) position — position shifts every time a row is
        // removed (siblings renumber down), which would otherwise silently
        // reassign one row's identity (and any scores already entered
        // against it) to whatever content now lands on its old number.
        $existingById = ClassRecordAssessment::with('dates')
            ->where('class_record_quarter_id', $quarter->id)
            ->get()
            ->keyBy('id');

        // The Setup tab always resubmits every leaf's complete row set on
        // every save (see the co-teacher note above) — so a per-category
        // count of THIS payload is the true, current number of assessments
        // under each category, which may exceed its configured
        // max_assessments cap (nothing enforces that cap). isMajor() must
        // divide by the larger of the two, never just the stale cap.
        $countsByCategory = $assessmentsInput->countBy(fn ($item) => (int) $item['grading_category_id']);

        // Type and is_major are always derived server-side — never trusted from
        // the client (the grading category already identifies the type)
        $items = $assessmentsInput->map(function ($item) use ($categories, $existingById, $countsByCategory) {
            $category = $categories[$item['grading_category_id']];

            $item['is_graded'] = array_key_exists('is_graded', $item) ? (bool) $item['is_graded'] : true;
            if ($item['is_graded'] && (! isset($item['max_score']) || (float) $item['max_score'] <= 0)) {
                throw ValidationException::withMessages([
                    'assessments' => "\"{$item['title']}\" requires a Max Score greater than zero because it is graded.",
                ]);
            }
            $item['max_score'] = $item['is_graded'] ? (float) $item['max_score'] : 0;
            $item['assessment_type'] = WatRuleService::deriveType($category->code, (int) $item['assessment_number']);
            $item['is_major'] = WatRuleService::isMajor(
                $item['assessment_type'],
                $category,
                $countsByCategory[(int) $item['grading_category_id']] ?? 1
            );
            $item['_existing'] = ! empty($item['id']) ? $existingById->get($item['id']) : null;
            $item['activity_dates'] = collect($item['activity_dates'] ?? [$item['activity_date']])
                ->push($item['activity_date'])
                ->map(fn ($date) => Carbon::parse($date)->toDateString())
                ->unique()
                ->sort()
                ->values()
                ->all();
            $item['activity_date'] = $item['activity_dates'][0];
            $existingDates = $item['_existing']?->activityDateStrings() ?? collect();
            $item['_added_dates'] = collect($item['activity_dates'])->diff($existingDates)->values()->all();
            $item['_date_changed'] = collect($item['activity_dates'])->values()->all() !== $existingDates->values()->all();

            return $item;
        });

        // WAT rule: plot no later than 12:00 NN Wednesday preceding the week of
        // implementation — leaves Wednesday afternoon onward for coordinator/CID
        // Chief review (admins may correct entries past the deadline)
        if (! $this->isAdmin()) {
            foreach ($items as $item) {
                foreach ($item['_added_dates'] as $date) {
                    if (WatRuleService::violatesPlottingDeadline($date)) {
                        $when = Carbon::parse($date)->format('M d, Y');
                        $deadline = WatRuleService::plottingDeadline($date)->format('D, M d, Y \a\t 12:00 NN');

                        return response()->json([
                            'message' => "\"{$item['title']}\" includes {$when}, but the plotting deadline for that week was {$deadline}. Assessments must be plotted no later than 12:00 NN of the Wednesday before their week — same-week plotting is not allowed.",
                        ], 422);
                    }
                }
            }
        }

        // WAT daily/weekly caps — graded assessments only, pooled with any
        // Science Core/Elective synthetic sections at this section's grade
        // (they cut across homerooms, so their assessments and this
        // section's own assessments share one budget — see
        // WatRuleService::poolSectionIds()). Never pooled with sibling real
        // homerooms. Long Test/Quarterly Exam entries inside a configured
        // exam window are exempt: every subject legitimately sits its final
        // exam in the same campus-wide days, which isn't the cramming these
        // caps guard against.
        //
        // Only rows that are genuinely NEW (no id yet) or being RE-DATED
        // (_date_changed) can push a day/week over the cap — an existing,
        // unchanged row must never count twice. Every save resubmits the
        // whole quarter (including rows nobody touched), so without this
        // filter an unrelated edit (e.g. adding a new row on a different
        // date) would recompute an already-over-cap day's total from
        // scratch via $replacedIds-then-re-add and incorrectly block the
        // entire save — even though that day's real, already-saved content
        // isn't changing at all.
        $grade = $classRecord->section?->levelid;
        if ($grade !== null) {
            $datedGraded = $items
                ->filter(fn ($item) => $item['is_graded'] && (empty($item['id']) || $item['_date_changed']))
                ->flatMap(fn ($item) => collect($item['activity_dates'])
                    ->reject(fn ($date) => WatRuleService::isExamExempt(
                        $item['assessment_type'],
                        $classRecord->school_year_id,
                        $q,
                        $date
                    ))
                    ->map(fn ($date) => [
                        'activity_date' => $date,
                        'is_major' => $item['is_major'],
                        'is_graded' => true,
                        'assessment_key' => ! empty($item['id'])
                            ? $item['id']
                            : 'new:'.$item['grading_category_id'].':'.$item['assessment_number'],
                        'section_id' => $classRecord->section_id,
                        'subject_id' => $classRecord->subject_id,
                        'subject_type' => $classRecord->subject?->subject_type,
                    ]));
            $replacedIds = $items
                ->filter(fn ($item) => $item['_existing'] && $item['_date_changed'])
                ->pluck('_existing.id')
                ->all();

            foreach ($datedGraded->groupBy('activity_date') as $date => $group) {
                $counts = WatRuleService::gradeCountsOnDate(
                    $classRecord->section_id,
                    $grade,
                    $classRecord->school_year_id,
                    $date,
                    $replacedIds,
                    $group->all()
                );
                $graded = $counts['graded'];
                $major = $counts['major'];
                $formatted = Carbon::parse($date)->format('M d, Y');

                if ($graded > WatRuleService::DAILY_GRADED_MAX) {
                    return response()->json([
                        'message' => "This section would have {$graded} graded assessments on {$formatted} — the WAT limit is ".WatRuleService::DAILY_GRADED_MAX.' graded assessments per day.',
                    ], 422);
                }
                if ($major > WatRuleService::DAILY_MAJOR_MAX) {
                    return response()->json([
                        'message' => "This section would have {$major} major assessments on {$formatted} — the WAT limit is ".WatRuleService::DAILY_MAJOR_MAX.' major assessments per day.',
                    ], 422);
                }
            }

            $byWeek = $datedGraded->groupBy(fn ($i) => Carbon::parse($i['activity_date'])->startOfWeek(Carbon::MONDAY)->toDateString());
            foreach ($byWeek as $weekStart => $group) {
                $counts = WatRuleService::gradeCountsInWeek(
                    $classRecord->section_id,
                    $grade,
                    $classRecord->school_year_id,
                    $weekStart,
                    $replacedIds,
                    $group->all()
                );
                $graded = $counts['graded'];
                $major = $counts['major'];
                $label = Carbon::parse($weekStart)->format('M d').'–'.Carbon::parse($weekStart)->addDays(4)->format('M d, Y');

                if ($graded > WatRuleService::WEEKLY_GRADED_MAX) {
                    return response()->json([
                        'message' => "This section would have {$graded} graded assessments in the week of {$label} — the WAT limit is ".WatRuleService::WEEKLY_GRADED_MAX.' graded assessments per week.',
                    ], 422);
                }
                if ($major > WatRuleService::WEEKLY_MAJOR_MAX) {
                    return response()->json([
                        'message' => "This section would have {$major} major assessments in the week of {$label} — the WAT limit is ".WatRuleService::WEEKLY_MAJOR_MAX.' major assessments per week.',
                    ], 422);
                }
            }
        }

        // Schedule-day rule: faculty may only plot on days the subject meets
        // this section (422 on changed dates, matching the deadline rule);
        // admins stay warn-only so they can plot make-up/special dates. Long
        // Test/Quarterly Exam entries inside a configured exam window are
        // exempt too — final exams commonly run a special block schedule
        // that doesn't match the regular weekly rotation.
        //
        // Checked against the ITEM'S OWN leaf category subject_id, not the
        // class record's default subject_id — on a shared PEHM record, PE /
        // Health / Music each keep their own weekly schedule, so a
        // co-teacher's assessment must be checked against their own
        // subject's meeting days, not whichever subject created the record.
        // A leaf with no subject_id (the normal, non-shared case) falls back
        // to the record's own subject, unchanged from before.
        $warnings = [];
        $meetsByDate = [];
        foreach ($items as $item) {
            $itemSubjectId = $categories[$item['grading_category_id']]->subject_id ?? $classRecord->subject_id;
            foreach ($item['activity_dates'] as $date) {
                if (WatRuleService::isExamExempt($item['assessment_type'], $classRecord->school_year_id, $q, $date)) {
                    continue;
                }
                $meetsKey = $itemSubjectId.'|'.$date;
                if (! array_key_exists($meetsKey, $meetsByDate)) {
                    $meetsByDate[$meetsKey] = WatRuleService::meetsOnDate(
                        $itemSubjectId,
                        $classRecord->section_id,
                        $classRecord->school_year_id,
                        $date
                    );
                }
                if ($meetsByDate[$meetsKey] !== false) {
                    continue;
                }
                $day = Carbon::parse($date)->format('l, M d');
                if (! $this->isAdmin() && in_array($date, $item['_added_dates'], true)) {
                    return response()->json([
                        'message' => "{$classRecord->subject_name} has no scheduled class with this section on {$day} — assessments can only be dated on days the class meets.",
                    ], 422);
                }
                $warning = "{$classRecord->subject_name} has no scheduled class with this section on {$day} — double-check the date.";
                if (! in_array($warning, $warnings, true)) {
                    $warnings[] = $warning;
                }
            }
        }

        foreach ($items->filter(fn ($item) => $item['_existing']) as $item) {
            $removedCurrentWeekDates = $item['_existing']->activityDateStrings()
                ->diff($item['activity_dates'])
                ->filter(fn ($date) => WatRuleService::isWithinScheduledWeek($date))
                ->values();

            if ($removedCurrentWeekDates->isNotEmpty()) {
                return response()->json([
                    'message' => "Cannot remove {$removedCurrentWeekDates->implode(', ')} from \"{$item['title']}\" because the assessment is announced for the current week. Request deletion for the specific date instead.",
                    'errors' => ['assessments' => ['One or more current-week assessment dates require approval before removal.']],
                ], 422);
            }
        }

        // ── Removed rows: any existing assessment for this quarter whose id is
        //    absent from the incoming payload was deleted by the user. Block
        //    the whole save if any of those already have scores entered.
        //    On a co-teacher-scoped save, only the requester's OWN categories
        //    are ever candidates for deletion — a sibling subject's rows were
        //    filtered out of $items above (unchanged, not "removed"), and
        //    must never be diffed away here regardless of the payload.
        $incomingIds = $items->pluck('id')->filter()->map(fn ($id) => (int) $id)->unique();
        $deletionCandidates = $isCoTeacherScoped
            ? $existingById->filter(fn ($assessment) => in_array((int) $assessment->grading_category_id, $editableCategoryIds, true))
            : $existingById;
        $toDeleteIds = $deletionCandidates->keys()->diff($incomingIds)->values();

        if ($toDeleteIds->isNotEmpty()) {
            // Once plotted (dated), an assessment is considered announced to
            // students during its scheduled week — it can no longer be
            // silently dropped from the grid while that week is current.
            // Deletion during the scheduled week requires ACIDAA approval via
            // requestDeletion()/approveDeletionRequest(), not a plain save.
            // Outside that window (week hasn't arrived yet, or has already
            // passed) a direct delete is allowed, same as an unplotted row.
            $plotted = $existingById->only($toDeleteIds->all())
                ->filter(fn ($assessment) => $assessment->activityDateStrings()
                    ->contains(fn ($date) => WatRuleService::isWithinScheduledWeek($date)));
            if ($plotted->isNotEmpty()) {
                $titles = $plotted->pluck('title')->implode('", "');

                return response()->json([
                    'message' => "Cannot save — \"{$titles}\" is plotted for this week and announced to students. Use \"Request Deletion\" on that row instead; it can only be removed once the Assistant CID Chief for Academic Affairs approves.",
                    'errors' => ['assessments' => ['One or more removed assessments are already plotted.']],
                ], 422);
            }

            $blockedIds = ClassRecordScore::whereIn('class_record_assessment_id', $toDeleteIds)
                ->distinct()
                ->pluck('class_record_assessment_id');

            if ($blockedIds->isNotEmpty()) {
                $titles = $existingById->only($blockedIds->all())->pluck('title')->implode('", "');

                return response()->json([
                    'message' => "Cannot save — \"{$titles}\" already has scores entered. Clear its scores first before removing it.",
                    'errors' => ['assessments' => ['One or more removed assessments already have scores entered.']],
                ], 422);
            }
        }

        $becomingNonGradedIds = $items
            ->filter(fn ($item) => $item['_existing']?->is_graded && ! $item['is_graded'])
            ->pluck('_existing')
            ->pluck('id')
            ->values();
        $scoresToRemove = $becomingNonGradedIds->isEmpty()
            ? 0
            : ClassRecordScore::whereIn('class_record_assessment_id', $becomingNonGradedIds)->count();

        if ($scoresToRemove > 0 && ! ($validated['confirm_non_graded_score_removal'] ?? false)) {
            return response()->json([
                'message' => "Changing to Non-graded will permanently remove {$scoresToRemove} saved student score(s).",
                'requires_confirmation' => true,
                'score_count' => $scoresToRemove,
            ], 409);
        }

        $upserted = [];
        DB::transaction(function () use ($items, $quarter, $toDeleteIds, $becomingNonGradedIds, &$upserted) {
            if ($toDeleteIds->isNotEmpty()) {
                ClassRecordAssessment::whereIn('id', $toDeleteIds)->delete();
            }
            if ($becomingNonGradedIds->isNotEmpty()) {
                ClassRecordScore::whereIn('class_record_assessment_id', $becomingNonGradedIds)->delete();
            }

            // Process in (category, number) order so a row moving INTO a slot
            // never collides with the unique index before the row that used
            // to occupy that slot has already moved (or been deleted) out of it.
            $ordered = $items->sortBy(fn ($item) => [$item['grading_category_id'], $item['assessment_number']]);

            foreach ($ordered as $i => $item) {
                $hasDate = ! empty($item['activity_date']);
                $existing = $item['_existing'];

                $attributes = [
                    'class_record_quarter_id' => $quarter->id,
                    'grading_category_id' => $item['grading_category_id'],
                    'assessment_number' => $item['assessment_number'],
                    'title' => $item['title'],
                    'assessment_type' => $item['assessment_type'],
                    'is_graded' => $item['is_graded'],
                    'is_major' => $item['is_major'],
                    'activity_date' => $item['activity_date'] ?? null,
                    'plotted_at' => ! $hasDate ? null
                        : ($item['_date_changed'] || ! $existing?->plotted_at ? now() : $existing->plotted_at),
                    'max_score' => $item['max_score'],
                    'sort_order' => $item['sort_order'] ?? $i,
                ];

                if ($existing) {
                    $existing->update($attributes);
                    $assessment = $existing;
                } else {
                    $assessment = ClassRecordAssessment::create($attributes);
                }

                $assessment->syncActivityDates($item['activity_dates']);
                $upserted[] = $assessment;
            }
        });

        return response()->json([
            'message' => count($upserted).' assessment(s) saved.',
            'warnings' => $warnings,
            'data' => $upserted,
        ]);
    }

    // ── POST /class-records/{cr}/quarters/{q}/assessments/plot ──────────────

    /**
     * Append one calendar-plotted assessment without replacing the rest of
     * the quarter Setup. Optional targets apply that same assessment to other
     * editable class records for the same subject.
     */
    public function plot(Request $request, ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($classRecord->canEdit(Auth::user()), 403);

        $validated = $request->validate([
            'grading_category_id' => 'required|integer|exists:grading_categories,id',
            'title' => 'required|string|max:255',
            'is_graded' => 'sometimes|boolean',
            'activity_date' => 'required|date',
            'activity_dates' => 'sometimes|array|min:1',
            'activity_dates.*' => 'required|date|distinct',
            'max_score' => 'nullable|numeric|min:0',
            'target_class_record_ids' => 'sometimes|array',
            'target_class_record_ids.*' => 'integer|distinct|exists:class_records,id',
        ]);

        $isGraded = (bool) ($validated['is_graded'] ?? true);
        if ($isGraded && (! isset($validated['max_score']) || (float) $validated['max_score'] <= 0)) {
            throw ValidationException::withMessages([
                'max_score' => 'Max Score must be greater than zero for a graded assessment.',
            ]);
        }

        $result = $this->plottingService->plot(
            $classRecord,
            $q,
            (int) $validated['grading_category_id'],
            [
                'title' => $validated['title'],
                'is_graded' => $isGraded,
                'activity_date' => $validated['activity_date'],
                'activity_dates' => $validated['activity_dates'] ?? [$validated['activity_date']],
                'max_score' => $isGraded ? (float) $validated['max_score'] : 0,
            ],
            $validated['target_class_record_ids'] ?? [],
            Auth::user(),
        );

        return response()->json([
            'message' => count($result['created']).' assessment placement(s) saved.',
            ...$result,
        ], 201);
    }

    // ── POST /class-records/{cr}/quarters/{q}/assessments/copy-from ──────────

    public function copyFrom(Request $request, ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($classRecord->canEdit(Auth::user()), 403);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $targetQuarter = $this->resolveQuarter($classRecord, $q);
        abort_if($targetQuarter->is_locked, 403, 'Quarter is locked.');

        $validated = $request->validate([
            'source_quarter' => 'required|integer|in:1,2,3,4',
        ]);

        $sourceQ = (int) $validated['source_quarter'];
        abort_if($sourceQ === $q, 422, 'Source and target quarters must be different.');

        $sourceQuarter = ClassRecordQuarter::where('class_record_id', $classRecord->id)
            ->where('quarter', $sourceQ)
            ->first();

        abort_if(! $sourceQuarter, 422, "Quarter {$sourceQ} has no assessments to copy from.");

        $sourceOptionId = $sourceQuarter->grading_option_id ?? $classRecord->grading_option_id;
        $targetOptionId = $targetQuarter->grading_option_id ?? $classRecord->grading_option_id;
        abort_if(
            (int) $sourceOptionId !== (int) $targetOptionId,
            422,
            "Quarter {$sourceQ} uses a different grading option than Quarter {$q}; assessments cannot be copied between them.",
        );

        $sourceAssessments = ClassRecordAssessment::where('class_record_quarter_id', $sourceQuarter->id)
            ->orderBy('sort_order')
            ->get();

        abort_if($sourceAssessments->isEmpty(), 422, "Quarter {$sourceQ} has no assessments yet.");

        $targetCount = ClassRecordAssessment::where('class_record_quarter_id', $targetQuarter->id)->count();
        abort_if($targetCount > 0, 422, 'This quarter already has assessments. Clear them before copying.');

        $copied = DB::transaction(function () use ($sourceAssessments, $targetQuarter) {
            return $sourceAssessments->map(fn ($src) => ClassRecordAssessment::create([
                'class_record_quarter_id' => $targetQuarter->id,
                'grading_category_id' => $src->grading_category_id,
                'assessment_number' => $src->assessment_number,
                'title' => $src->title,
                'assessment_type' => $src->assessment_type,
                'is_graded' => $src->is_graded,
                'is_major' => $src->is_major,
                'activity_date' => null,
                'plotted_at' => null,
                'max_score' => $src->max_score,
                'sort_order' => $src->sort_order,
            ]))->values()->all();
        });

        return response()->json([
            'message' => count($copied).' assessment(s) copied from Q'.$sourceQ.'.',
            'data' => $copied,
        ]);
    }

    // ── POST /class-records/{cr}/quarters/{q}/assessments/copy-from-record ───

    public function copyFromRecord(Request $request, ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($classRecord->canEdit(Auth::user()), 403);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $targetQuarter = $this->resolveQuarter($classRecord, $q);
        abort_if($targetQuarter->is_locked, 403, 'Quarter is locked.');

        $validated = $request->validate([
            'source_class_record_id' => 'required|integer|exists:class_records,id',
            'source_quarter' => 'required|integer|in:1,2,3,4',
        ]);

        $sourceRecord = ClassRecord::findOrFail($validated['source_class_record_id']);
        abort_unless(
            $sourceRecord->canEdit(Auth::user()),
            403,
            'You do not have access to that class record.'
        );
        abort_if(
            $sourceRecord->isArchived(),
            422,
            'That class record has been archived and can no longer be used as a copy source.'
        );
        abort_if(
            strtolower($sourceRecord->subject_name) !== strtolower($classRecord->subject_name),
            422,
            'Source class record must be for the same subject.'
        );

        $sourceQuarter = ClassRecordQuarter::where('class_record_id', $sourceRecord->id)
            ->where('quarter', $validated['source_quarter'])
            ->first();

        abort_if(! $sourceQuarter, 422, 'Source quarter not found.');

        $sourceOptionId = $sourceQuarter->grading_option_id ?? $sourceRecord->grading_option_id;
        $targetOptionId = $targetQuarter->grading_option_id ?? $classRecord->grading_option_id;
        abort_if(
            (int) $sourceOptionId !== (int) $targetOptionId,
            422,
            'The source quarter uses a different grading option than this quarter; assessments cannot be copied between them.',
        );

        $sourceAssessments = ClassRecordAssessment::where('class_record_quarter_id', $sourceQuarter->id)
            ->orderBy('sort_order')
            ->get();

        abort_if($sourceAssessments->isEmpty(), 422, 'Source quarter has no assessments.');

        $targetCount = ClassRecordAssessment::where('class_record_quarter_id', $targetQuarter->id)->count();
        abort_if($targetCount > 0, 422, 'This quarter already has assessments. Clear them before copying.');

        $copied = DB::transaction(function () use ($sourceAssessments, $targetQuarter) {
            return $sourceAssessments->map(fn ($src) => ClassRecordAssessment::create([
                'class_record_quarter_id' => $targetQuarter->id,
                'grading_category_id' => $src->grading_category_id,
                'assessment_number' => $src->assessment_number,
                'title' => $src->title,
                'assessment_type' => $src->assessment_type,
                'is_graded' => $src->is_graded,
                'is_major' => $src->is_major,
                'activity_date' => null,
                'plotted_at' => null,
                'max_score' => $src->max_score,
                'sort_order' => $src->sort_order,
            ]))->values()->all();
        });

        return response()->json([
            'message' => count($copied).' assessment(s) copied.',
            'data' => $copied,
        ]);
    }

    // ── POST /class-records/{cr}/quarters/{q}/assessments/apply-to-sections ──

    /**
     * Push this quarter's assessment setup (including dates) out to other
     * sections the same teacher has for this subject. Unlike copyFrom/
     * copyFromRecord (which strip dates so nothing can violate WAT), this
     * carries dates over and validates each target independently against
     * WatRuleService — an ineligible or WAT-violating target is skipped with
     * a reason rather than aborting the whole batch.
     */
    public function applyToSections(Request $request, ClassRecord $classRecord, int $q): JsonResponse
    {
        $user = Auth::user();
        abort_unless($classRecord->canEdit($user), 403);
        abort_if($classRecord->isArchived(), 422, 'This class record has been archived.');
        abort_if(! $classRecord->isCurrentSchoolYear(), 422, 'Only a current-school-year Setup can be applied.');

        $sourceQuarter = $this->resolveQuarter($classRecord, $q);
        abort_if($sourceQuarter->is_locked, 422, 'Unlock this quarter before applying its Setup.');

        $validated = $request->validate([
            'target_class_record_ids' => 'required|array|min:1',
            'target_class_record_ids.*' => 'integer|distinct|exists:class_records,id',
        ]);

        $sourceAssessments = ClassRecordAssessment::with(['gradingCategory', 'dates'])
            ->where('class_record_quarter_id', $sourceQuarter->id)
            ->orderBy('sort_order')
            ->get();

        abort_if($sourceAssessments->isEmpty(), 422, 'This quarter has no assessments to apply.');

        // A co-teacher on a shared PEHM record may only push the categories
        // assigned to their own subject. Normal single-teacher records and
        // class-record administrators continue to apply the complete Setup.
        $isCategoryScoped = ! $this->isAdmin() && $classRecord->coTeachers()->exists();
        if ($isCategoryScoped) {
            $sourceAssessments = $sourceAssessments
                ->filter(fn (ClassRecordAssessment $assessment) => $assessment->gradingCategory
                    && $classRecord->canEdit($user, $assessment->gradingCategory->subject_id))
                ->values();
            abort_if($sourceAssessments->isEmpty(), 422, 'This quarter has no assessments under your assigned PEHM subject to apply.');
        }

        $sourceOptionId = $sourceQuarter->effectiveGradingOptionId();
        $sourceCategoryIds = $sourceAssessments->pluck('grading_category_id')->unique()->values();

        $applied = [];
        $skipped = [];

        foreach ($validated['target_class_record_ids'] as $targetId) {
            if ((int) $targetId === $classRecord->id) {
                continue;
            }

            $target = ClassRecord::with(['section:id,levelid,sectionname', 'coTeachers'])->find($targetId);
            $label = $target?->year_level_section ?: "Class Record #{$targetId}";

            if (! $target?->canEdit($user)) {
                $skipped[] = ['class_record_id' => $targetId, 'label' => $label, 'reason' => 'You do not have access to that class record.'];

                continue;
            }
            if ($target->isArchived()) {
                $skipped[] = ['class_record_id' => $targetId, 'label' => $label, 'reason' => 'That class record has been archived.'];

                continue;
            }
            if ((int) $target->section_id === (int) $classRecord->section_id) {
                $skipped[] = ['class_record_id' => $targetId, 'label' => $label, 'reason' => 'Select a class record from another section.'];

                continue;
            }
            $sameSubject = $classRecord->subject_id && $target->subject_id
                ? (int) $classRecord->subject_id === (int) $target->subject_id
                : strcasecmp((string) $target->subject_name, (string) $classRecord->subject_name) === 0;
            if (! $sameSubject) {
                $skipped[] = ['class_record_id' => $targetId, 'label' => $label, 'reason' => 'Different subject.'];

                continue;
            }
            if (! $target->isCurrentSchoolYear()) {
                $skipped[] = ['class_record_id' => $targetId, 'label' => $label, 'reason' => 'That class record is from a past school year and is read-only.'];

                continue;
            }

            $targetQuarter = ClassRecordQuarter::firstOrCreate(
                ['class_record_id' => $target->id, 'quarter' => $q],
                ['is_locked' => false]
            );

            if ($targetQuarter->is_locked) {
                $skipped[] = ['class_record_id' => $targetId, 'label' => $label, 'reason' => 'Quarter is locked.'];

                continue;
            }
            if ((int) $targetQuarter->effectiveGradingOptionId() !== (int) $sourceOptionId) {
                $skipped[] = ['class_record_id' => $targetId, 'label' => $label, 'reason' => 'Uses a different grading option.'];

                continue;
            }

            $unauthorizedCategory = $sourceAssessments->first(fn (ClassRecordAssessment $assessment) => ! $assessment->gradingCategory
                || ! $target->canEdit($user, $assessment->gradingCategory->subject_id));
            if ($unauthorizedCategory) {
                $name = $unauthorizedCategory->gradingCategory?->name ?? 'one or more categories';
                $skipped[] = ['class_record_id' => $targetId, 'label' => $label, 'reason' => "You do not have edit access to {$name} on that class record."];

                continue;
            }

            try {
                $result = DB::transaction(function () use (
                    $targetQuarter,
                    $target,
                    $q,
                    $sourceAssessments,
                    $sourceOptionId,
                    $sourceCategoryIds,
                    $isCategoryScoped
                ) {
                    $lockedQuarter = ClassRecordQuarter::whereKey($targetQuarter->id)->lockForUpdate()->firstOrFail();
                    if ($lockedQuarter->is_locked) {
                        return ['reason' => 'Quarter is locked.', 'warnings' => []];
                    }
                    if ((int) $lockedQuarter->effectiveGradingOptionId() !== (int) $sourceOptionId) {
                        return ['reason' => 'Uses a different grading option.', 'warnings' => []];
                    }

                    $existing = ClassRecordAssessment::where('class_record_quarter_id', $lockedQuarter->id)
                        ->lockForUpdate()
                        ->get();
                    $conflicts = $isCategoryScoped
                        ? $existing->whereIn('grading_category_id', $sourceCategoryIds)
                        : $existing;
                    if ($conflicts->isNotEmpty()) {
                        return [
                            'reason' => $isCategoryScoped
                                ? 'Your PEHM subject already has assessments in this quarter.'
                                : 'Already has assessments this quarter.',
                            'warnings' => [],
                        ];
                    }

                    $check = $this->checkWatForApply($target, $q, $sourceAssessments);
                    if ($check['reason']) {
                        return $check;
                    }

                    foreach ($sourceAssessments as $src) {
                        $assessment = ClassRecordAssessment::create([
                            'class_record_quarter_id' => $lockedQuarter->id,
                            'grading_category_id' => $src->grading_category_id,
                            'assessment_number' => $src->assessment_number,
                            'title' => $src->title,
                            'assessment_type' => $src->assessment_type,
                            'is_graded' => $src->is_graded,
                            'is_major' => $src->is_major,
                            'activity_date' => $src->activity_date,
                            'plotted_at' => $src->activity_date ? now() : null,
                            'max_score' => $src->max_score,
                            'sort_order' => $src->sort_order,
                        ]);
                        $assessment->syncActivityDates($src->activityDateStrings()->all());
                    }

                    return $check;
                }, 3);
            } catch (QueryException) {
                $result = [
                    'reason' => 'The target Setup changed while this request was being processed. Review it and try again.',
                    'warnings' => [],
                ];
            }

            if ($result['reason']) {
                $skipped[] = ['class_record_id' => $targetId, 'label' => $label, 'reason' => $result['reason']];

                continue;
            }

            $applied[] = [
                'class_record_id' => $targetId,
                'label' => $label,
                'count' => $sourceAssessments->count(),
                'warnings' => $result['warnings'],
            ];
        }

        return response()->json([
            'applied' => $applied,
            'skipped' => $skipped,
        ]);
    }

    /**
     * Simulates applying $sourceAssessments (as-is, dates included) onto
     * $target's quarter $q and reports the first WAT violation found, if
     * any — same rules as the upsert() endpoint's own validation, just
     * evaluated against a different section's existing load. Schedule-day
     * mismatches are collected as non-blocking warnings, matching their
     * warn-only status everywhere else in the module.
     *
     * @return array{reason: ?string, warnings: array<string>}
     */
    private function checkWatForApply(ClassRecord $target, int $q, $sourceAssessments): array
    {
        if (! $this->isAdmin()) {
            foreach ($sourceAssessments as $src) {
                foreach ($src->activityDateStrings() as $date) {
                    if (WatRuleService::violatesPlottingDeadline($date)) {
                        $when = Carbon::parse($date)->format('M d, Y');
                        $deadline = WatRuleService::plottingDeadline($date)->format('D, M d, Y \a\t 12:00 NN');

                        return [
                            'reason' => "\"{$src->title}\" includes {$when}, but the plotting deadline for that week was {$deadline}.",
                            'warnings' => [],
                        ];
                    }
                }
            }
        }

        $grade = $target->section?->levelid;
        if ($grade !== null) {
            $datedGraded = $sourceAssessments
                ->filter(fn ($assessment) => $assessment->is_graded)
                ->flatMap(fn ($assessment) => $assessment->activityDateStrings()
                    ->reject(fn ($date) => WatRuleService::isExamExempt(
                        $assessment->assessment_type,
                        $target->school_year_id,
                        $q,
                        $date
                    ))
                    ->map(fn ($date) => [
                        'activity_date' => $date,
                        'is_major' => $assessment->is_major,
                        'is_graded' => true,
                        'assessment_key' => $assessment->id,
                        'section_id' => $target->section_id,
                        'subject_id' => $target->subject_id,
                        'subject_type' => $target->subject?->subject_type,
                    ]));

            foreach ($datedGraded->groupBy('activity_date') as $date => $group) {
                $counts = WatRuleService::gradeCountsOnDate(
                    $target->section_id,
                    $grade,
                    $target->school_year_id,
                    $date,
                    [],
                    $group->all()
                );
                $graded = $counts['graded'];
                $major = $counts['major'];
                $formatted = Carbon::parse($date)->format('M d, Y');

                if ($graded > WatRuleService::DAILY_GRADED_MAX) {
                    return [
                        'reason' => "Would have {$graded} graded assessments on {$formatted} — the WAT limit is ".WatRuleService::DAILY_GRADED_MAX.' per day.',
                        'warnings' => [],
                    ];
                }
                if ($major > WatRuleService::DAILY_MAJOR_MAX) {
                    return [
                        'reason' => "Would have {$major} major assessments on {$formatted} — the WAT limit is ".WatRuleService::DAILY_MAJOR_MAX.' per day.',
                        'warnings' => [],
                    ];
                }
            }

            $byWeek = $datedGraded->groupBy(fn ($item) => Carbon::parse($item['activity_date'])
                ->startOfWeek(Carbon::MONDAY)->toDateString());
            foreach ($byWeek as $weekStart => $group) {
                $counts = WatRuleService::gradeCountsInWeek(
                    $target->section_id,
                    $grade,
                    $target->school_year_id,
                    $weekStart,
                    [],
                    $group->all()
                );
                $graded = $counts['graded'];
                $major = $counts['major'];
                $label = Carbon::parse($weekStart)->format('M d').'–'.Carbon::parse($weekStart)->addDays(4)->format('M d, Y');

                if ($graded > WatRuleService::WEEKLY_GRADED_MAX) {
                    return [
                        'reason' => "Would have {$graded} graded assessments in the week of {$label} — the WAT limit is ".WatRuleService::WEEKLY_GRADED_MAX.' per week.',
                        'warnings' => [],
                    ];
                }
                if ($major > WatRuleService::WEEKLY_MAJOR_MAX) {
                    return [
                        'reason' => "Would have {$major} major assessments in the week of {$label} — the WAT limit is ".WatRuleService::WEEKLY_MAJOR_MAX.' per week.',
                        'warnings' => [],
                    ];
                }
            }
        }

        $warnings = [];
        $meetsByDate = [];
        foreach ($sourceAssessments as $item) {
            $itemSubjectId = $item->gradingCategory?->subject_id ?? $target->subject_id;
            foreach ($item->activityDateStrings() as $date) {
                if (WatRuleService::isExamExempt($item->assessment_type, $target->school_year_id, $q, $date)) {
                    continue;
                }
                $meetsKey = $itemSubjectId.'|'.$date;
                if (! array_key_exists($meetsKey, $meetsByDate)) {
                    $meetsByDate[$meetsKey] = WatRuleService::meetsOnDate($itemSubjectId, $target->section_id, $target->school_year_id, $date);
                }
                if ($meetsByDate[$meetsKey] === false) {
                    $day = Carbon::parse($date)->format('l, M d');
                    $warning = "{$target->subject_name} has no scheduled class with this section on {$day}.";
                    if (! $this->isAdmin()) {
                        return ['reason' => $warning, 'warnings' => []];
                    }
                    if (! in_array($warning, $warnings, true)) {
                        $warnings[] = $warning;
                    }
                }
            }
        }

        return ['reason' => null, 'warnings' => $warnings];
    }

    // ── POST /class-records/{cr}/quarters/{q}/assessments/{assessment}/request-deletion ──

    /**
     * A plotted (dated) assessment is considered announced to students while
     * its scheduled week is current — removing it during that window is no
     * longer a self-service action. This files a pending request for the
     * ACIDAA (Assistant CID Chief for Academic Affairs) to approve or reject
     * via the Approval Inbox; the assessment itself is untouched until then.
     * Callable regardless of week (kept permissive), but the Setup tab only
     * routes here when the row's activity_date falls within the current
     * scheduled week — otherwise it deletes the row directly.
     */
    public function requestDeletion(Request $request, ClassRecord $classRecord, int $q, ClassRecordAssessment $assessment): JsonResponse
    {
        abort_unless($classRecord->canEdit(Auth::user()), 403);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_if($quarter->is_locked, 403, 'Quarter is locked.');
        abort_unless($assessment->class_record_quarter_id === $quarter->id, 404);

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
            'activity_date' => 'nullable|date',
        ]);

        $assessment->load('dates');
        $requestedDate = ! empty($validated['activity_date'])
            ? $assessment->dates->first(function ($date) use ($assessment, $validated) {
                $effectiveDate = $date->is_primary && $assessment->activity_date
                    ? $assessment->activity_date->toDateString()
                    : $date->activity_date->toDateString();

                return $effectiveDate === Carbon::parse($validated['activity_date'])->toDateString();
            })
            : null;

        abort_if(
            ! empty($validated['activity_date']) && ! $requestedDate,
            422,
            'That assessment date no longer exists.'
        );
        abort_if(
            ! $requestedDate && $assessment->activityDateStrings()->isEmpty(),
            422,
            'This assessment isn\'t plotted yet — remove it directly from the Setup tab.'
        );

        if (! $requestedDate) {
            $hasScores = ClassRecordScore::where('class_record_assessment_id', $assessment->id)
                ->whereNotNull('score')->exists();
            abort_if($hasScores, 422, 'This assessment already has scores entered. Clear its scores first before requesting deletion.');
        }

        abort_if(
            ClassRecordAssessmentDeletionRequest::where('class_record_assessment_id', $assessment->id)
                ->when($requestedDate, fn ($query) => $query->where(function ($pending) use ($requestedDate) {
                    $pending->whereNull('class_record_assessment_date_id')
                        ->orWhere('class_record_assessment_date_id', $requestedDate->id);
                }))
                ->where('status', 'pending')->exists(),
            422,
            $requestedDate
                ? 'A deletion request is already pending for this assessment date.'
                : 'A deletion request is already pending for this assessment.'
        );

        $category = $assessment->gradingCategory;

        $deletionRequest = ClassRecordAssessmentDeletionRequest::create([
            'class_record_assessment_id' => $assessment->id,
            'class_record_assessment_date_id' => $requestedDate?->id,
            'class_record_quarter_id' => $quarter->id,
            'title' => $assessment->title,
            'assessment_number' => $assessment->assessment_number,
            'category_code' => $category?->code,
            'category_name' => $category?->name,
            'activity_date' => $requestedDate
                ? Carbon::parse($validated['activity_date'])->toDateString()
                : $assessment->activity_date,
            'max_score' => $assessment->max_score,
            'requested_by_id' => Auth::id(),
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        if ($acidaa = $this->resolveAcidaaHolder($classRecord->school_year_id)) {
            NotificationService::notifyUser(
                $acidaa,
                'Assessment Deletion Request',
                "#{$deletionRequest->id}",
                'Pending your approval',
                route('approvals.inbox'),
                "\"{$assessment->title}\" — {$classRecord->subject_name}"
            );
        }

        // On a shared (e.g. PEHM) record, courtesy-notify the OTHER
        // co-teachers that one of their shared record's assessments has a
        // pending deletion request — they don't approve it (only ACIDAA
        // does), but it's their shared record too.
        $otherTeacherIds = array_diff($classRecord->allTeacherIds(), [Auth::id()]);
        if ($otherTeacherIds) {
            foreach (User::whereIn('id', $otherTeacherIds)->get() as $coTeacher) {
                NotificationService::notifyUser(
                    $coTeacher,
                    'Assessment Deletion Request',
                    "#{$deletionRequest->id}",
                    'Pending approval',
                    route('class-records.page.show', $classRecord),
                    "\"{$assessment->title}\" on your shared class record — requested by ".Auth::user()->name,
                );
            }
        }

        return response()->json([
            'message' => 'Deletion request submitted — it will be removed once the Assistant CID Chief for Academic Affairs approves.',
        ]);
    }

    /**
     * Approve a pending deletion request: deletes the assessment (its
     * scores would cascade, but requestDeletion() never lets a scored
     * assessment reach 'pending' in the first place) and notifies the
     * requesting teacher.
     */
    public function approveDeletionRequest(Request $request, ClassRecordAssessmentDeletionRequest $deletionRequest)
    {
        $assessment = $deletionRequest->assessment;
        $classRecord = $deletionRequest->quarter?->classRecord;

        if ($assessment && $deletionRequest->assessmentDate) {
            $remainingDates = $assessment->activityDateStrings()
                ->reject(fn ($date) => $date === $deletionRequest->activity_date->toDateString())
                ->values()
                ->all();
            $assessment->syncActivityDates($remainingDates);
        } elseif ($assessment) {
            $assessment->delete();
        }

        $deletionRequest->update([
            'status' => 'approved',
            'reviewed_by_id' => Auth::id(),
            'reviewed_at' => now(),
            'review_remarks' => $request->input('remarks'),
        ]);

        if ($teacher = $deletionRequest->requestedBy) {
            NotificationService::notifyUser(
                $teacher,
                'Assessment Deletion Request',
                "#{$deletionRequest->id}",
                'Approved',
                $classRecord ? route('class-records.show', $classRecord->id) : route('class-records.index'),
                $deletionRequest->class_record_assessment_date_id
                    ? "\"{$deletionRequest->title}\" was removed from {$deletionRequest->activity_date->format('M d, Y')}."
                    : "\"{$deletionRequest->title}\" has been deleted."
            );
        }

        return back()->with(
            'success',
            $deletionRequest->class_record_assessment_date_id
                ? 'Assessment date removed.'
                : 'Assessment deleted.'
        );
    }

    /**
     * Decline a pending deletion request: the assessment is left untouched
     * and can be re-requested later; the teacher is notified with the
     * reviewer's remarks.
     */
    public function declineDeletionRequest(Request $request, ClassRecordAssessmentDeletionRequest $deletionRequest)
    {
        $classRecord = $deletionRequest->quarter?->classRecord;

        $deletionRequest->update([
            'status' => 'rejected',
            'reviewed_by_id' => Auth::id(),
            'reviewed_at' => now(),
            'review_remarks' => $request->input('reason'),
        ]);

        if ($teacher = $deletionRequest->requestedBy) {
            NotificationService::notifyUser(
                $teacher,
                'Assessment Deletion Request',
                "#{$deletionRequest->id}",
                'Declined',
                $classRecord ? route('class-records.show', $classRecord->id) : route('class-records.index'),
                $request->input('reason')
            );
        }

        return back()->with('success', 'Deletion request declined.');
    }

    /**
     * The current holder of the ACIDAA (Assistant CID Chief for Academic
     * Affairs) designation, resolved the same way IPCRWorkflowService does:
     * via the current school year's load_assignments row carrying the
     * ACIDAA designation code. Kept local to Class Record rather than
     * reaching into IPCRWorkflowService's private lookup.
     */
    private function resolveAcidaaHolder(?int $schoolYearId): ?User
    {
        $schoolYearId ??= SchoolYear::where('is_current', true)->value('id');
        if (! $schoolYearId) {
            return null;
        }

        $designationIds = Designation::whereIn('code', ['ACIDAA', 'SUP-ACIDAA'])
            ->orWhere('name', 'like', 'Assistant CID Chief for Academic Affairs%')
            ->pluck('id');

        $userId = LoadAssignment::whereIn('designation_id', $designationIds)
            ->where('school_year_id', $schoolYearId)
            ->latest('id')
            ->value('user_id');

        return $userId ? User::find($userId) : null;
    }
}
