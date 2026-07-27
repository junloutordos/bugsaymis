<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordScore;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\User;
use App\Services\ClassRecord\WatRuleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClassRecordAssessmentController extends Controller
{
    private function isAdmin(): bool
    {
        return Auth::user()->hasPermission('class-records.admin');
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
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);

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
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);
        abort_if(! $classRecord->section_id, 422, 'This class record has no section linked.');

        $rows = ClassRecordAssessment::select([
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
            ])
            ->join('class_record_quarters as crq', 'class_record_assessments.class_record_quarter_id', '=', 'crq.id')
            ->join('class_records as cr', 'crq.class_record_id', '=', 'cr.id')
            ->join('grading_categories as gc', 'class_record_assessments.grading_category_id', '=', 'gc.id')
            ->where('cr.section_id', $classRecord->section_id)
            ->where('cr.school_year_id', $classRecord->school_year_id)
            ->whereNotNull('class_record_assessments.activity_date')
            ->orderBy('class_record_assessments.activity_date')
            ->get();

        $teacherIds = $rows->pluck('teacher_id')->filter()->unique()->values()->toArray();
        $teachers   = User::whereIn('id', $teacherIds)->get(['id', 'name'])->keyBy('id');

        $days = $rows->groupBy(fn ($row) => $row->activity_date instanceof \Carbon\Carbon
                ? $row->activity_date->toDateString()
                : (string) $row->activity_date)
            ->map(function ($items, $date) use ($classRecord, $teachers) {
                return [
                    'date'         => $date,
                    'count'        => $items->count(),
                    'graded_count' => $items->where('is_graded', true)->count(),
                    'major_count'  => $items->where('is_graded', true)->where('is_major', true)->count(),
                    'items' => $items->map(fn ($row) => [
                        'id'              => $row->id,
                        'title'           => $row->title,
                        'subject_name'    => $row->subject_name,
                        'teacher_name'    => $teachers[$row->teacher_id]?->name,
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
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);

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
        foreach ($validated['assessments'] as $item) {
            abort_unless(
                in_array((int) $item['grading_category_id'], $leafIds, true),
                422,
                'One or more assessments reference a category that is not part of this quarter\'s grading option.',
            );
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

        // WAT rule: plot no later than the Friday preceding the week of
        // implementation (admins may correct entries past the deadline)
        if (! $this->isAdmin()) {
            foreach ($items as $item) {
                if (! $item['_date_changed'] || empty($item['activity_date'])) {
                    continue;
                }
                if (WatRuleService::violatesPlottingDeadline($item['activity_date'])) {
                    $when     = Carbon::parse($item['activity_date'])->format('M d, Y');
                    $deadline = WatRuleService::plottingDeadline($item['activity_date'])->format('D, M d, Y');
                    return response()->json([
                        'message' => "\"{$item['title']}\" is dated {$when}, but the plotting deadline for that week was {$deadline}. Assessments must be plotted no later than the Friday before their week — same-week plotting is not allowed.",
                    ], 422);
                }
            }
        }

        // WAT daily/weekly caps — graded assessments only, section-wide.
        // Long Test/Quarterly Exam entries inside a configured exam window are
        // exempt: every subject legitimately sits its final exam in the same
        // campus-wide days, which isn't the cramming these caps guard against.
        if ($classRecord->section_id) {
            $replacedIds = $items->pluck('_existing')->filter()->pluck('id')->all();
            $datedGraded = $items->filter(fn ($i) => ! empty($i['activity_date']) && $i['is_graded']
                && ! WatRuleService::isExamExempt($i['assessment_type'], $classRecord->school_year_id, $q, $i['activity_date']));

            foreach ($datedGraded->groupBy('activity_date') as $date => $group) {
                $counts    = WatRuleService::sectionCountsOnDate($classRecord->section_id, $classRecord->school_year_id, $date, $replacedIds);
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
                $counts = WatRuleService::sectionCountsInWeek($classRecord->section_id, $classRecord->school_year_id, $weekStart, $replacedIds);
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
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);
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
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $targetQuarter = $this->resolveQuarter($classRecord, $q);
        abort_if($targetQuarter->is_locked, 403, 'Quarter is locked.');

        $validated = $request->validate([
            'source_class_record_id' => 'required|integer|exists:class_records,id',
            'source_quarter'         => 'required|integer|in:1,2,3,4',
        ]);

        $sourceRecord = ClassRecord::findOrFail($validated['source_class_record_id']);
        abort_unless(
            $this->isAdmin() || $sourceRecord->teacher_id === Auth::id(),
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
}
