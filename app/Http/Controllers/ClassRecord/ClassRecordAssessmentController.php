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
use App\Services\ClassRecord\ClassRecordMonitorScopeService;
use App\Services\ClassRecord\WatRuleService;
use App\Services\NotificationService;
use App\Services\PersonNameFormatter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClassRecordAssessmentController extends Controller
{
    public function __construct(private readonly ClassRecordMonitorScopeService $monitorScope)
    {
    }

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

        $quarter     = $this->resolveQuarter($classRecord, $q);
        $assessments = ClassRecordAssessment::with('gradingCategory')
            ->where('class_record_quarter_id', $quarter->id)
            ->orderBy('sort_order')
            ->get();

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
        $rows = ClassRecordAssessment::schoolYearScopeQuery($classRecord->school_year_id)
            ->whereIn('cr.section_id', $poolSectionIds)
            ->whereNotNull('class_record_assessments.activity_date')
            ->join('grading_categories as gc', 'class_record_assessments.grading_category_id', '=', 'gc.id')
            ->orderBy('class_record_assessments.activity_date')
            ->get([
                'class_record_assessments.id',
                'class_record_assessments.title',
                'class_record_assessments.activity_date',
                'class_record_assessments.assessment_type',
                'class_record_assessments.is_graded',
                'class_record_assessments.is_major',
                'cr.id as class_record_id',
                'cr.subject_name',
                'cr.teacher_id',
                'gc.code as category_code',
            ]);

        $teacherIds = $rows->pluck('teacher_id')->filter()->unique()->values()->toArray();
        $teachers   = User::whereIn('id', $teacherIds)
            ->get(['id', 'name', 'prenominal_title', 'postnominal_title'])
            ->keyBy('id');
        $nameFormatter = new PersonNameFormatter();

        $days = $rows->groupBy(fn ($row) => $row->activity_date instanceof \Carbon\Carbon
                ? $row->activity_date->toDateString()
                : (string) $row->activity_date)
            ->map(function ($items, $date) use ($classRecord, $teachers, $nameFormatter) {
                return [
                    'date'         => $date,
                    'count'        => $items->count(),
                    'graded_count' => $items->where('is_graded', true)->count(),
                    'major_count'  => $items->where('is_graded', true)->where('is_major', true)->count(),
                    'items' => $items->map(fn ($row) => [
                        'id'              => $row->id,
                        'title'           => $row->title,
                        'subject_name'    => $row->subject_name,
                        'teacher_name'    => $teachers[$row->teacher_id]
                            ? $nameFormatter->withTitles($teachers[$row->teacher_id], $teachers[$row->teacher_id]->name ?? '')
                            : null,
                        'category_code'   => $row->category_code,
                        'assessment_type' => $row->assessment_type,
                        'is_graded'       => (bool) $row->is_graded,
                        'is_major'        => (bool) $row->is_major,
                        'is_own_record'   => $row->class_record_id === $classRecord->id,
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
            'assessments'                         => 'required|array|min:1',
            'assessments.*.id'                     => 'nullable|integer',
            'assessments.*.grading_category_id'   => 'required|integer|exists:grading_categories,id',
            'assessments.*.assessment_number'      => 'required|integer|min:1',
            'assessments.*.title'                  => 'required|string|max:255',
            'assessments.*.is_graded'              => 'sometimes|boolean',
            'assessments.*.activity_date'          => 'required|date',
            'assessments.*.max_score'              => 'required|numeric|min:0.01',
            'assessments.*.sort_order'             => 'sometimes|integer|min:0',
        ]);

        $categories = GradingCategory::whereIn(
            'id',
            collect($validated['assessments'])->pluck('grading_category_id')->unique()
        )->get()->keyBy('id');

        // Assessments may only attach to LEAF categories of the grading option
        // in force for THIS quarter (per-quarter override, else record default).
        $optionId = $quarter->grading_option_id ?? $classRecord->grading_option_id;
        $option = GradingOption::with('categories')->find($optionId);
        $leafIds = $option ? $option->leafCategories()->pluck('id')->map(fn ($id) => (int) $id)->all() : [];
        $isAdminUser = $this->isAdmin();
        foreach ($validated['assessments'] as $item) {
            abort_unless(
                in_array((int) $item['grading_category_id'], $leafIds, true),
                422,
                'One or more assessments reference a category that is not part of this quarter\'s grading option.',
            );

            // On a shared (e.g. PEHM) record, a leaf tagged with a subject_id
            // may only be written to by that subject's teacher — this is what
            // keeps the PE teacher from editing Music's assessments and vice
            // versa. Leaves with no subject_id (the normal case) stay scoped
            // to any of the record's teachers, same as canEdit(null) above.
            $category = $categories[$item['grading_category_id']];
            if (! $isAdminUser) {
                abort_unless(
                    $classRecord->canEdit(Auth::user(), $category->subject_id),
                    403,
                    "You do not have edit access to \"{$category->name}\" on this class record.",
                );
            }
        }

        // Existing rows are matched by their stable primary key, not by
        // (category, number) position — position shifts every time a row is
        // removed (siblings renumber down), which would otherwise silently
        // reassign one row's identity (and any scores already entered
        // against it) to whatever content now lands on its old number.
        $existingById = ClassRecordAssessment::where('class_record_quarter_id', $quarter->id)->get()->keyBy('id');

        // Type and is_major are always derived server-side — never trusted from
        // the client (the grading category already identifies the type)
        $items = collect($validated['assessments'])->map(function ($item) use ($categories, $existingById) {
            $category = $categories[$item['grading_category_id']];

            $item['is_graded']       = array_key_exists('is_graded', $item) ? (bool) $item['is_graded'] : true;
            $item['assessment_type'] = WatRuleService::deriveType($category->code, (int) $item['assessment_number']);
            $item['is_major']        = WatRuleService::isMajor($item['assessment_type'], $category);
            $item['_existing'] = ! empty($item['id']) ? $existingById->get($item['id']) : null;
            $item['_date_changed'] = ! empty($item['activity_date']) && ! (
                $item['_existing']?->activity_date
                && $item['_existing']->activity_date->toDateString() === Carbon::parse($item['activity_date'])->toDateString()
            );
            return $item;
        });

        // WAT rule: plot no later than 12:00 NN Friday preceding the week of
        // implementation — leaves the Friday afternoon for coordinator/CID
        // Chief review (admins may correct entries past the deadline)
        if (! $this->isAdmin()) {
            foreach ($items as $item) {
                if (! $item['_date_changed'] || empty($item['activity_date'])) {
                    continue;
                }
                if (WatRuleService::violatesPlottingDeadline($item['activity_date'])) {
                    $when     = Carbon::parse($item['activity_date'])->format('M d, Y');
                    $deadline = WatRuleService::plottingDeadline($item['activity_date'])->format('D, M d, Y \a\t 12:00 NN');
                    return response()->json([
                        'message' => "\"{$item['title']}\" is dated {$when}, but the plotting deadline for that week was {$deadline}. Assessments must be plotted no later than 12:00 NN of the Friday before their week — same-week plotting is not allowed.",
                    ], 422);
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
        $grade = $classRecord->section?->levelid;
        if ($grade !== null) {
            $replacedIds = $items->pluck('_existing')->filter()->pluck('id')->all();
            $datedGraded = $items->filter(fn ($i) => ! empty($i['activity_date']) && $i['is_graded']
                && ! WatRuleService::isExamExempt($i['assessment_type'], $classRecord->school_year_id, $q, $i['activity_date']));

            foreach ($datedGraded->groupBy('activity_date') as $date => $group) {
                $counts    = WatRuleService::gradeCountsOnDate($classRecord->section_id, $grade, $classRecord->school_year_id, $date, $replacedIds);
                $graded    = $counts['graded'] + $group->count();
                $major     = $counts['major'] + $group->where('is_major', true)->count();
                $formatted = Carbon::parse($date)->format('M d, Y');

                if ($graded > WatRuleService::DAILY_GRADED_MAX) {
                    return response()->json([
                        'message' => "This section would have {$graded} graded assessments on {$formatted} — the WAT limit is " . WatRuleService::DAILY_GRADED_MAX . ' graded assessments per day.',
                    ], 422);
                }
                if ($major > WatRuleService::DAILY_MAJOR_MAX) {
                    return response()->json([
                        'message' => "This section would have {$major} major assessments on {$formatted} — the WAT limit is " . WatRuleService::DAILY_MAJOR_MAX . ' major assessments per day.',
                    ], 422);
                }
            }

            $byWeek = $datedGraded->groupBy(fn ($i) => Carbon::parse($i['activity_date'])->startOfWeek(Carbon::MONDAY)->toDateString());
            foreach ($byWeek as $weekStart => $group) {
                $counts = WatRuleService::gradeCountsInWeek($classRecord->section_id, $grade, $classRecord->school_year_id, $weekStart, $replacedIds);
                $graded = $counts['graded'] + $group->count();
                $major  = $counts['major'] + $group->where('is_major', true)->count();
                $label  = Carbon::parse($weekStart)->format('M d') . '–' . Carbon::parse($weekStart)->addDays(4)->format('M d, Y');

                if ($graded > WatRuleService::WEEKLY_GRADED_MAX) {
                    return response()->json([
                        'message' => "This section would have {$graded} graded assessments in the week of {$label} — the WAT limit is " . WatRuleService::WEEKLY_GRADED_MAX . ' graded assessments per week.',
                    ], 422);
                }
                if ($major > WatRuleService::WEEKLY_MAJOR_MAX) {
                    return response()->json([
                        'message' => "This section would have {$major} major assessments in the week of {$label} — the WAT limit is " . WatRuleService::WEEKLY_MAJOR_MAX . ' major assessments per week.',
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
        $warnings = [];
        $meetsByDate = [];
        foreach ($items->filter(fn ($i) => ! empty($i['activity_date'])) as $item) {
            if (WatRuleService::isExamExempt($item['assessment_type'], $classRecord->school_year_id, $q, $item['activity_date'])) {
                continue;
            }
            $date = $item['activity_date'];
            if (! array_key_exists($date, $meetsByDate)) {
                $meetsByDate[$date] = WatRuleService::meetsOnDate(
                    $classRecord->subject_id,
                    $classRecord->section_id,
                    $classRecord->school_year_id,
                    $date
                );
            }
            if ($meetsByDate[$date] !== false) {
                continue;
            }
            $day = Carbon::parse($date)->format('l, M d');
            if (! $this->isAdmin() && $item['_date_changed']) {
                return response()->json([
                    'message' => "{$classRecord->subject_name} has no scheduled class with this section on {$day} — assessments can only be dated on days the class meets.",
                ], 422);
            }
            $warning = "{$classRecord->subject_name} has no scheduled class with this section on {$day} — double-check the date.";
            if (! in_array($warning, $warnings, true)) {
                $warnings[] = $warning;
            }
        }

        // ── Removed rows: any existing assessment for this quarter whose id is
        //    absent from the incoming payload was deleted by the user. Block
        //    the whole save if any of those already have scores entered.
        $incomingIds = $items->pluck('id')->filter()->map(fn ($id) => (int) $id)->unique();
        $toDeleteIds = $existingById->keys()->diff($incomingIds)->values();

        if ($toDeleteIds->isNotEmpty()) {
            // Once plotted (dated), an assessment is considered announced to
            // students during its scheduled week — it can no longer be
            // silently dropped from the grid while that week is current.
            // Deletion during the scheduled week requires ACIDAA approval via
            // requestDeletion()/approveDeletionRequest(), not a plain save.
            // Outside that window (week hasn't arrived yet, or has already
            // passed) a direct delete is allowed, same as an unplotted row.
            $plotted = $existingById->only($toDeleteIds->all())
                ->filter(fn ($a) => $a->activity_date !== null)
                ->filter(fn ($a) => WatRuleService::isWithinScheduledWeek($a->activity_date->toDateString()));
            if ($plotted->isNotEmpty()) {
                $titles = $plotted->pluck('title')->implode('", "');
                return response()->json([
                    'message' => "Cannot save — \"{$titles}\" is plotted for this week and announced to students. Use \"Request Deletion\" on that row instead; it can only be removed once the Assistant CID Chief for Academic Affairs approves.",
                    'errors'  => ['assessments' => ['One or more removed assessments are already plotted.']],
                ], 422);
            }

            $blockedIds = ClassRecordScore::whereIn('class_record_assessment_id', $toDeleteIds)
                ->distinct()
                ->pluck('class_record_assessment_id');

            if ($blockedIds->isNotEmpty()) {
                $titles = $existingById->only($blockedIds->all())->pluck('title')->implode('", "');
                return response()->json([
                    'message' => "Cannot save — \"{$titles}\" already has scores entered. Clear its scores first before removing it.",
                    'errors'  => ['assessments' => ['One or more removed assessments already have scores entered.']],
                ], 422);
            }
        }

        $upserted = [];
        DB::transaction(function () use ($items, $quarter, $toDeleteIds, &$upserted) {
            if ($toDeleteIds->isNotEmpty()) {
                ClassRecordAssessment::whereIn('id', $toDeleteIds)->delete();
            }

            // Process in (category, number) order so a row moving INTO a slot
            // never collides with the unique index before the row that used
            // to occupy that slot has already moved (or been deleted) out of it.
            $ordered = $items->sortBy(fn ($item) => [$item['grading_category_id'], $item['assessment_number']]);

            foreach ($ordered as $i => $item) {
                $hasDate  = ! empty($item['activity_date']);
                $existing = $item['_existing'];

                $attributes = [
                    'class_record_quarter_id' => $quarter->id,
                    'grading_category_id'     => $item['grading_category_id'],
                    'assessment_number'       => $item['assessment_number'],
                    'title'                   => $item['title'],
                    'assessment_type'         => $item['assessment_type'],
                    'is_graded'               => $item['is_graded'],
                    'is_major'                => $item['is_major'],
                    'activity_date'           => $item['activity_date'] ?? null,
                    'plotted_at'              => ! $hasDate ? null
                        : ($item['_date_changed'] || ! $existing?->plotted_at ? now() : $existing->plotted_at),
                    'max_score'               => $item['max_score'],
                    'sort_order'              => $item['sort_order'] ?? $i,
                ];

                if ($existing) {
                    $existing->update($attributes);
                    $upserted[] = $existing;
                } else {
                    $upserted[] = ClassRecordAssessment::create($attributes);
                }
            }
        });

        return response()->json([
            'message'  => count($upserted) . ' assessment(s) saved.',
            'warnings' => $warnings,
            'data'     => $upserted,
        ]);
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
                'grading_category_id'     => $src->grading_category_id,
                'assessment_number'       => $src->assessment_number,
                'title'                   => $src->title,
                'assessment_type'         => $src->assessment_type,
                'is_graded'               => $src->is_graded,
                'is_major'                => $src->is_major,
                'activity_date'           => null,
                'plotted_at'              => null,
                'max_score'               => $src->max_score,
                'sort_order'              => $src->sort_order,
            ]))->values()->all();
        });

        return response()->json([
            'message' => count($copied) . ' assessment(s) copied from Q' . $sourceQ . '.',
            'data'    => $copied,
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
            'source_quarter'         => 'required|integer|in:1,2,3,4',
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
                'grading_category_id'     => $src->grading_category_id,
                'assessment_number'       => $src->assessment_number,
                'title'                   => $src->title,
                'assessment_type'         => $src->assessment_type,
                'is_graded'               => $src->is_graded,
                'is_major'                => $src->is_major,
                'activity_date'           => null,
                'plotted_at'              => null,
                'max_score'               => $src->max_score,
                'sort_order'              => $src->sort_order,
            ]))->values()->all();
        });

        return response()->json([
            'message' => count($copied) . ' assessment(s) copied.',
            'data'    => $copied,
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
        abort_unless($classRecord->canEdit(Auth::user()), 403);

        $sourceQuarter = $this->resolveQuarter($classRecord, $q);

        $validated = $request->validate([
            'target_class_record_ids'   => 'required|array|min:1',
            'target_class_record_ids.*' => 'integer|distinct|exists:class_records,id',
        ]);

        $sourceAssessments = ClassRecordAssessment::where('class_record_quarter_id', $sourceQuarter->id)
            ->orderBy('sort_order')
            ->get();

        abort_if($sourceAssessments->isEmpty(), 422, 'This quarter has no assessments to apply.');

        $sourceOptionId = $sourceQuarter->effectiveGradingOptionId();

        $applied = [];
        $skipped = [];

        foreach ($validated['target_class_record_ids'] as $targetId) {
            if ((int) $targetId === $classRecord->id) {
                continue;
            }

            $target = ClassRecord::with('section:id,levelid,sectionname')->find($targetId);
            $label  = $target?->year_level_section ?: "Class Record #{$targetId}";

            if (! $target?->canEdit(Auth::user())) {
                $skipped[] = ['class_record_id' => $targetId, 'label' => $label, 'reason' => 'You do not have access to that class record.'];
                continue;
            }
            if ($target->isArchived()) {
                $skipped[] = ['class_record_id' => $targetId, 'label' => $label, 'reason' => 'That class record has been archived.'];
                continue;
            }
            if (strtolower($target->subject_name) !== strtolower($classRecord->subject_name)) {
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

            $existingCount = ClassRecordAssessment::where('class_record_quarter_id', $targetQuarter->id)->count();
            if ($existingCount > 0) {
                $skipped[] = ['class_record_id' => $targetId, 'label' => $label, 'reason' => 'Already has assessments this quarter.'];
                continue;
            }

            $check = $this->checkWatForApply($target, $q, $sourceAssessments);
            if ($check['reason']) {
                $skipped[] = ['class_record_id' => $targetId, 'label' => $label, 'reason' => $check['reason']];
                continue;
            }

            DB::transaction(function () use ($sourceAssessments, $targetQuarter) {
                foreach ($sourceAssessments as $src) {
                    ClassRecordAssessment::create([
                        'class_record_quarter_id' => $targetQuarter->id,
                        'grading_category_id'     => $src->grading_category_id,
                        'assessment_number'       => $src->assessment_number,
                        'title'                   => $src->title,
                        'assessment_type'         => $src->assessment_type,
                        'is_graded'               => $src->is_graded,
                        'is_major'                => $src->is_major,
                        'activity_date'           => $src->activity_date,
                        'plotted_at'              => $src->activity_date ? now() : null,
                        'max_score'               => $src->max_score,
                        'sort_order'              => $src->sort_order,
                    ]);
                }
            });

            $applied[] = [
                'class_record_id' => $targetId,
                'label'           => $label,
                'count'           => $sourceAssessments->count(),
                'warnings'        => $check['warnings'],
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
                if (! $src->activity_date) {
                    continue;
                }
                $date = $src->activity_date->toDateString();
                if (WatRuleService::violatesPlottingDeadline($date)) {
                    $when     = $src->activity_date->format('M d, Y');
                    $deadline = WatRuleService::plottingDeadline($date)->format('D, M d, Y \a\t 12:00 NN');
                    return [
                        'reason'   => "\"{$src->title}\" is dated {$when}, but the plotting deadline for that week was {$deadline}.",
                        'warnings' => [],
                    ];
                }
            }
        }

        $grade = $target->section?->levelid;
        if ($grade !== null) {
            $datedGraded = $sourceAssessments->filter(fn ($i) => $i->activity_date && $i->is_graded
                && ! WatRuleService::isExamExempt($i->assessment_type, $target->school_year_id, $q, $i->activity_date->toDateString()));

            foreach ($datedGraded->groupBy(fn ($i) => $i->activity_date->toDateString()) as $date => $group) {
                $counts    = WatRuleService::gradeCountsOnDate($target->section_id, $grade, $target->school_year_id, $date);
                $graded    = $counts['graded'] + $group->count();
                $major     = $counts['major'] + $group->where('is_major', true)->count();
                $formatted = Carbon::parse($date)->format('M d, Y');

                if ($graded > WatRuleService::DAILY_GRADED_MAX) {
                    return [
                        'reason'   => "Would have {$graded} graded assessments on {$formatted} — the WAT limit is " . WatRuleService::DAILY_GRADED_MAX . ' per day.',
                        'warnings' => [],
                    ];
                }
                if ($major > WatRuleService::DAILY_MAJOR_MAX) {
                    return [
                        'reason'   => "Would have {$major} major assessments on {$formatted} — the WAT limit is " . WatRuleService::DAILY_MAJOR_MAX . ' per day.',
                        'warnings' => [],
                    ];
                }
            }

            $byWeek = $datedGraded->groupBy(fn ($i) => $i->activity_date->copy()->startOfWeek(Carbon::MONDAY)->toDateString());
            foreach ($byWeek as $weekStart => $group) {
                $counts = WatRuleService::gradeCountsInWeek($target->section_id, $grade, $target->school_year_id, $weekStart);
                $graded = $counts['graded'] + $group->count();
                $major  = $counts['major'] + $group->where('is_major', true)->count();
                $label  = Carbon::parse($weekStart)->format('M d') . '–' . Carbon::parse($weekStart)->addDays(4)->format('M d, Y');

                if ($graded > WatRuleService::WEEKLY_GRADED_MAX) {
                    return [
                        'reason'   => "Would have {$graded} graded assessments in the week of {$label} — the WAT limit is " . WatRuleService::WEEKLY_GRADED_MAX . ' per week.',
                        'warnings' => [],
                    ];
                }
                if ($major > WatRuleService::WEEKLY_MAJOR_MAX) {
                    return [
                        'reason'   => "Would have {$major} major assessments in the week of {$label} — the WAT limit is " . WatRuleService::WEEKLY_MAJOR_MAX . ' per week.',
                        'warnings' => [],
                    ];
                }
            }
        }

        $warnings    = [];
        $meetsByDate = [];
        foreach ($sourceAssessments->filter(fn ($i) => $i->activity_date) as $item) {
            $date = $item->activity_date->toDateString();
            if (WatRuleService::isExamExempt($item->assessment_type, $target->school_year_id, $q, $date)) {
                continue;
            }
            if (! array_key_exists($date, $meetsByDate)) {
                $meetsByDate[$date] = WatRuleService::meetsOnDate($target->subject_id, $target->section_id, $target->school_year_id, $date);
            }
            if ($meetsByDate[$date] === false) {
                $day     = Carbon::parse($date)->format('l, M d');
                $warning = "{$target->subject_name} has no scheduled class with this section on {$day} — double-check the date.";
                if (! in_array($warning, $warnings, true)) {
                    $warnings[] = $warning;
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

        abort_if(! $assessment->activity_date, 422, 'This assessment isn\'t plotted yet — remove it directly from the Setup tab.');

        $hasScores = ClassRecordScore::where('class_record_assessment_id', $assessment->id)
            ->whereNotNull('score')->exists();
        abort_if($hasScores, 422, 'This assessment already has scores entered. Clear its scores first before requesting deletion.');

        abort_if(
            ClassRecordAssessmentDeletionRequest::where('class_record_assessment_id', $assessment->id)
                ->where('status', 'pending')->exists(),
            422,
            'A deletion request is already pending for this assessment.'
        );

        $validated = $request->validate(['reason' => 'required|string|max:1000']);

        $category = $assessment->gradingCategory;

        $deletionRequest = ClassRecordAssessmentDeletionRequest::create([
            'class_record_assessment_id' => $assessment->id,
            'class_record_quarter_id'    => $quarter->id,
            'title'                      => $assessment->title,
            'assessment_number'          => $assessment->assessment_number,
            'category_code'              => $category?->code,
            'category_name'              => $category?->name,
            'activity_date'              => $assessment->activity_date,
            'max_score'                  => $assessment->max_score,
            'requested_by_id'            => Auth::id(),
            'reason'                     => $validated['reason'],
            'status'                     => 'pending',
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

        if ($assessment) {
            $assessment->delete();
        }

        $deletionRequest->update([
            'status'          => 'approved',
            'reviewed_by_id'  => Auth::id(),
            'reviewed_at'     => now(),
            'review_remarks'  => $request->input('remarks'),
        ]);

        if ($teacher = $deletionRequest->requestedBy) {
            NotificationService::notifyUser(
                $teacher,
                'Assessment Deletion Request',
                "#{$deletionRequest->id}",
                'Approved',
                $classRecord ? route('class-records.show', $classRecord->id) : route('class-records.index'),
                "\"{$deletionRequest->title}\" has been deleted."
            );
        }

        return back()->with('success', 'Assessment deleted.');
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
            'status'          => 'rejected',
            'reviewed_by_id'  => Auth::id(),
            'reviewed_at'     => now(),
            'review_remarks'  => $request->input('reason'),
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
