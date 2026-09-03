<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordIlaDate;
use App\Models\ClassRecord\ClassRecordIlaRecord;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordScore;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Services\ClassRecord\ClassRecordMonitorScopeService;
use App\Services\ClassRecord\WatRuleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClassRecordIlaController extends Controller
{
    public function __construct(private readonly ClassRecordMonitorScopeService $monitorScope) {}

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

    /** ILA only applies to subjects that reserve an Independent Learning Period. */
    private function assertIlpSubject(ClassRecord $classRecord): void
    {
        abort_unless($classRecord->subject?->has_ilp, 403, "This subject doesn't have an Independent Learning Period — ILA is not applicable.");
    }

    private function resolveQuarter(ClassRecord $classRecord, int $q): ClassRecordQuarter
    {
        abort_unless(in_array($q, [1, 2, 3, 4]), 422, 'Quarter must be 1-4.');

        return ClassRecordQuarter::where('class_record_id', $classRecord->id)
            ->where('quarter', $q)
            ->firstOrFail();
    }

    // ── GET /class-records/{cr}/quarters/{q}/ila ──────────────────────────────

    public function index(ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($this->canView($classRecord), 403);
        $this->assertIlpSubject($classRecord);

        $quarter = $this->resolveQuarter($classRecord, $q);

        $dates = ClassRecordIlaDate::where('class_record_quarter_id', $quarter->id)
            ->orderBy('date')
            ->get(['id', 'date', 'is_auto_generated', 'sort_order', 'title', 'is_graded', 'class_record_assessment_id']);

        $records = ClassRecordIlaRecord::whereHas('ilaDate', fn ($sq) => $sq->where('class_record_quarter_id', $quarter->id)
        )
            ->get(['class_record_ila_date_id', 'class_record_student_id', 'status'])
            ->mapWithKeys(fn ($r) => [
            "{$r->class_record_student_id}_{$r->class_record_ila_date_id}" => $r->status,
        ]);

        return response()->json(['dates' => $dates, 'records' => $records]);
    }

    // ── POST /class-records/{cr}/quarters/{q}/ila/dates ───────────────────────

    public function storeDate(Request $request, ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($classRecord->canEdit(Auth::user()), 403);
        $this->assertIlpSubject($classRecord);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_if($quarter->is_locked, 403, 'Quarter is locked.');

        $validated = $request->validate([
            'date' => 'required|date',
        ]);

        $maxOrder = ClassRecordIlaDate::where('class_record_quarter_id', $quarter->id)
            ->max('sort_order') ?? 0;

        $date = ClassRecordIlaDate::firstOrCreate(
            ['class_record_quarter_id' => $quarter->id, 'date' => $validated['date']],
            ['sort_order' => $maxOrder + 1, 'is_auto_generated' => false]
        );

        return response()->json($date);
    }

    // ── DELETE /class-records/{cr}/quarters/{q}/ila/dates/{ilaDate} ───────────

    public function destroyDate(ClassRecord $classRecord, int $q, ClassRecordIlaDate $ilaDate): JsonResponse
    {
        abort_unless($classRecord->canEdit(Auth::user()), 403);
        $this->assertIlpSubject($classRecord);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_unless($ilaDate->class_record_quarter_id === $quarter->id, 404);
        abort_if($quarter->is_locked, 403, 'Quarter is locked.');

        $ilaDate->delete();

        return response()->json(['message' => 'Date removed.']);
    }

    // ── PATCH /class-records/{cr}/quarters/{q}/ila/dates/{ilaDate}/title ─────

    /**
     * Sets/edits the activity name for a not-yet-graded ILA date — purely
     * descriptive, shown in the WAT print's Assessment column in place of
     * the generic "Independent Learning Activity" placeholder. Once graded,
     * the real title lives on the linked class_record_assessments row
     * instead (set at grading time), so this is blocked for graded dates.
     */
    public function updateTitle(Request $request, ClassRecord $classRecord, int $q, ClassRecordIlaDate $ilaDate): JsonResponse
    {
        abort_unless($classRecord->canEdit(Auth::user()), 403);
        $this->assertIlpSubject($classRecord);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_unless($ilaDate->class_record_quarter_id === $quarter->id, 404);
        abort_if($quarter->is_locked, 403, 'Quarter is locked.');
        abort_if($ilaDate->is_graded, 422, 'This ILA date is already graded — its title is set from the graded assessment instead.');

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
        ]);

        $ilaDate->update(['title' => $validated['title'] ?: null]);

        return response()->json($ilaDate->fresh());
    }

    // ── POST /class-records/{cr}/quarters/{q}/ila/records ─────────────────────

    public function upsert(Request $request, ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($classRecord->canEdit(Auth::user()), 403);
        $this->assertIlpSubject($classRecord);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_if($quarter->is_locked, 403, 'Quarter is locked.');

        $validated = $request->validate([
            'records' => 'required|array|min:1',
            'records.*.date_id' => 'required|integer|exists:class_record_ila_dates,id',
            'records.*.student_id' => 'required|integer|exists:class_record_students,id',
            'records.*.status' => 'nullable|in:compliant,non_compliant',
        ]);

        // "exists" only proves the IDs are valid rows *somewhere* — without this,
        // a teacher could pass a date_id/student_id belonging to another class
        // record entirely and delete or overwrite someone else's ILA data.
        $dateIds = collect($validated['records'])->pluck('date_id')->unique();
        $validDateIds = ClassRecordIlaDate::whereIn('id', $dateIds)
            ->where('class_record_quarter_id', $quarter->id)
            ->pluck('id');
        abort_if($validDateIds->count() !== $dateIds->count(), 422, 'One or more dates do not belong to this quarter.');

        $studentIds = collect($validated['records'])->pluck('student_id')->unique();
        $validStudentIds = ClassRecordStudent::whereIn('id', $studentIds)
            ->where('class_record_quarter_id', $quarter->id)
            ->pluck('id');
        abort_if($validStudentIds->count() !== $studentIds->count(), 422, 'One or more students do not belong to this class record.');

        $now = now();
        $toUpsert = [];

        foreach ($validated['records'] as $item) {
            $status = $item['status'] ?? null;

            if ($status === null) {
                DB::table('class_record_ila_records')
                    ->where('class_record_ila_date_id', $item['date_id'])
                    ->where('class_record_student_id', $item['student_id'])
                    ->delete();
            } else {
                $toUpsert[] = [
                    'class_record_ila_date_id' => $item['date_id'],
                    'class_record_student_id' => $item['student_id'],
                    'status' => $status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($toUpsert) {
            DB::table('class_record_ila_records')->upsert(
                $toUpsert,
                ['class_record_ila_date_id', 'class_record_student_id'],
                ['status', 'updated_at']
            );
        }

        return response()->json(['message' => count($toUpsert).' record(s) saved.']);
    }

    // ── POST /class-records/{cr}/quarters/{q}/ila/dates/{ilaDate}/grade ───────

    /**
     * Elects a specific ILA date for grading — the same decision as plotting
     * any other WAT assessment, gated by the same rules: locked in no later
     * than 12:00 NN the Wednesday before its week (WatRuleService), and counted
     * against the section's daily/weekly graded & major caps. The date's
     * existing compliance marks seed the initial scores (compliant = full
     * credit, non-compliant = 0) so nothing already recorded is lost; the
     * teacher can still adjust individual scores afterward via the normal
     * WAT score entry.
     */
    public function gradeDate(Request $request, ClassRecord $classRecord, int $q, ClassRecordIlaDate $ilaDate): JsonResponse
    {
        abort_unless($classRecord->canEdit(Auth::user()), 403);
        $this->assertIlpSubject($classRecord);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_unless($ilaDate->class_record_quarter_id === $quarter->id, 404);
        abort_if($quarter->is_locked, 403, 'Quarter is locked.');
        abort_if($ilaDate->is_graded, 422, 'This ILA date is already graded.');

        $isAdmin = $this->isAdmin();
        $date = $ilaDate->date->toDateString();

        if (! $isAdmin && WatRuleService::violatesPlottingDeadline($date)) {
            $deadline = WatRuleService::plottingDeadline($date)->format('D, M d, Y \a\t 12:00 NN');
            abort(422, "Too late to grade this ILA date — the plotting deadline for its week was {$deadline}. The decision to grade an ILA session must be made no later than 12:00 NN of the Wednesday before its week, same as any other assessment.");
        }

        $validated = $request->validate([
            'grading_category_id' => 'required|integer|exists:grading_categories,id',
            'title' => 'required|string|max:255',
            'max_score' => 'required|numeric|min:0.01',
        ]);

        $category = GradingCategory::findOrFail($validated['grading_category_id']);
        $optionId = $quarter->grading_option_id ?? $classRecord->grading_option_id;
        $option = GradingOption::with('categories')->find($optionId);
        $leafIds = $option ? $option->leafCategories()->pluck('id')->map(fn ($id) => (int) $id)->all() : [];
        abort_unless(in_array($category->id, $leafIds, true), 422, "That category is not part of this quarter's grading option.");

        $grade = $classRecord->section?->levelid;
        abort_if($grade === null, 422, 'This class record has no section linked.');

        // Computed here (not just below, where it's needed again for the
        // actual row) so isMajor() can divide by the true post-insert count
        // for this category — ILA dates accumulate daily and can exceed the
        // category's configured max_assessments cap just like any other
        // assessment type.
        $nextNumber = 1 + (int) ClassRecordAssessment::where('class_record_quarter_id', $quarter->id)
            ->where('grading_category_id', $category->id)
            ->max('assessment_number');

        $assessmentType = WatRuleService::deriveType($category->code, 1);
        $isMajor = WatRuleService::isMajor($assessmentType, $category, $nextNumber);

        // Daily/weekly caps — pooled with Science Core/Elective synthetic
        // sections, same rule as any other plotted assessment. ILA dates are
        // only ever generated on days the schedule already meets, so the
        // schedule-day check (WatRuleService::meetsOnDate) is redundant here.
        $candidate = [[
            'assessment_key' => 'ila:'.$ilaDate->id,
            'activity_date' => $date,
            'section_id' => $classRecord->section_id,
            'subject_id' => $classRecord->subject_id,
            'subject_type' => $classRecord->subject?->subject_type,
            'is_graded' => true,
            'is_major' => $isMajor,
        ]];
        $dayCounts = WatRuleService::gradeCountsOnDate(
            $classRecord->section_id,
            $grade,
            $classRecord->school_year_id,
            $date,
            [],
            $candidate
        );
        if ($dayCounts['graded'] > WatRuleService::DAILY_GRADED_MAX) {
            abort(422, 'This section would have '.$dayCounts['graded']." graded assessments on {$date} — the WAT limit is ".WatRuleService::DAILY_GRADED_MAX.' graded assessments per day.');
        }
        if ($isMajor && $dayCounts['major'] > WatRuleService::DAILY_MAJOR_MAX) {
            abort(422, 'This section would have '.$dayCounts['major']." major assessments on {$date} — the WAT limit is ".WatRuleService::DAILY_MAJOR_MAX.' major assessments per day.');
        }

        $weekStart = Carbon::parse($date)->startOfWeek(Carbon::MONDAY)->toDateString();
        $weekCounts = WatRuleService::gradeCountsInWeek(
            $classRecord->section_id,
            $grade,
            $classRecord->school_year_id,
            $weekStart,
            [],
            $candidate
        );
        if ($weekCounts['graded'] > WatRuleService::WEEKLY_GRADED_MAX) {
            abort(422, 'This section would have '.$weekCounts['graded']." graded assessments in the week of {$weekStart} — the WAT limit is ".WatRuleService::WEEKLY_GRADED_MAX.' graded assessments per week.');
        }
        if ($isMajor && $weekCounts['major'] > WatRuleService::WEEKLY_MAJOR_MAX) {
            abort(422, 'This section would have '.$weekCounts['major']." major assessments in the week of {$weekStart} — the WAT limit is ".WatRuleService::WEEKLY_MAJOR_MAX.' major assessments per week.');
        }

        $nextSort = 1 + (int) ClassRecordAssessment::where('class_record_quarter_id', $quarter->id)->max('sort_order');

        $assessment = null;
        DB::transaction(function () use (&$assessment, $quarter, $category, $assessmentType, $isMajor, $nextNumber, $nextSort, $validated, $date, $ilaDate) {
            $assessment = ClassRecordAssessment::create([
                'class_record_quarter_id' => $quarter->id,
                'grading_category_id' => $category->id,
                'assessment_type' => $assessmentType,
                'is_graded' => true,
                'is_major' => $isMajor,
                'assessment_number' => $nextNumber,
                'title' => $validated['title'],
                'activity_date' => $date,
                'plotted_at' => now(),
                'max_score' => $validated['max_score'],
                'sort_order' => $nextSort,
            ]);

            $records = ClassRecordIlaRecord::where('class_record_ila_date_id', $ilaDate->id)->get();
            foreach ($records as $record) {
                if ($record->status === null) {
                    continue;
                }
                ClassRecordScore::create([
                    'class_record_student_id' => $record->class_record_student_id,
                    'class_record_assessment_id' => $assessment->id,
                    'score' => $record->status === 'compliant' ? $validated['max_score'] : 0,
                ]);
            }

            $ilaDate->update([
                'is_graded' => true,
                'class_record_assessment_id' => $assessment->id,
                'graded_by_id' => Auth::id(),
                'graded_decided_at' => now(),
            ]);
        });

        return response()->json(['ilaDate' => $ilaDate->fresh(), 'assessment' => $assessment]);
    }

    // ── DELETE /class-records/{cr}/quarters/{q}/ila/dates/{ilaDate}/grade ─────

    /**
     * Reverts an ILA date back to compliance-only — only while its plotting
     * window is still open (same deadline as grading it in the first place).
     * Once the Wednesday-noon deadline passes, the decision is locked, same as
     * any other WAT assessment.
     */
    public function ungradeDate(ClassRecord $classRecord, int $q, ClassRecordIlaDate $ilaDate): JsonResponse
    {
        abort_unless($classRecord->canEdit(Auth::user()), 403);
        $this->assertIlpSubject($classRecord);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_unless($ilaDate->class_record_quarter_id === $quarter->id, 404);
        abort_if($quarter->is_locked, 403, 'Quarter is locked.');
        abort_unless($ilaDate->is_graded, 422, 'This ILA date is not graded.');

        if (! $this->isAdmin() && WatRuleService::violatesPlottingDeadline($ilaDate->date->toDateString())) {
            abort(422, 'Too late to un-grade this ILA date — the plotting deadline for its week has passed.');
        }

        DB::transaction(function () use ($ilaDate) {
            $assessmentId = $ilaDate->class_record_assessment_id;
            $ilaDate->update([
                'is_graded' => false,
                'class_record_assessment_id' => null,
                'graded_by_id' => null,
                'graded_decided_at' => null,
            ]);
            if ($assessmentId) {
                ClassRecordAssessment::whereKey($assessmentId)->delete(); // cascades to class_record_scores
            }
        });

        return response()->json(['message' => 'ILA date reverted to non-graded.']);
    }
}
