<?php

namespace App\Services\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Append-only assessment creation used by calendar entry points.
 *
 * Unlike ClassRecordAssessmentController::upsert(), this service never treats
 * omitted assessments as deletions. It is therefore safe for a one-cell
 * calendar action and for applying that single assessment to other sections.
 */
class AssessmentPlottingService
{
    /**
     * Plot one assessment on the source record, then independently attempt the
     * same assessment on the requested same-subject target records.
     *
     * @return array{created: array, skipped: array}
     */
    public function plot(
        ClassRecord $source,
        int $quarterNumber,
        int $sourceCategoryId,
        array $attributes,
        array $targetRecordIds,
        User $user,
    ): array {
        $sourceCategory = $this->sourceCategory($source, $quarterNumber, $sourceCategoryId, $user);
        $created = [];
        $skipped = [];

        $assessment = $this->createForRecord(
            $source,
            $quarterNumber,
            $sourceCategory,
            $attributes,
            $user,
        );
        $created[] = $this->resultRow($source, $assessment);

        foreach (collect($targetRecordIds)->map(fn ($id) => (int) $id)->unique() as $targetId) {
            if ($targetId === (int) $source->id) {
                continue;
            }

            $target = ClassRecord::with(['section:id,levelid,sectionname', 'coTeachers'])
                ->find($targetId);
            $label = $target?->year_level_section ?: "Class Record #{$targetId}";

            try {
                $this->assertSameSubjectTarget($source, $target, $user);
                $targetCategory = $this->matchingTargetCategory(
                    $target,
                    $quarterNumber,
                    $sourceCategory,
                    $user,
                );
                $targetAssessment = $this->createForRecord(
                    $target,
                    $quarterNumber,
                    $targetCategory,
                    $attributes,
                    $user,
                );
                $created[] = $this->resultRow($target, $targetAssessment);
            } catch (ValidationException $exception) {
                $skipped[] = [
                    'class_record_id' => $targetId,
                    'label' => $label,
                    'reason' => collect($exception->errors())->flatten()->first()
                        ?? 'This assessment could not be plotted.',
                ];
            }
        }

        return compact('created', 'skipped');
    }

    private function sourceCategory(
        ClassRecord $record,
        int $quarterNumber,
        int $categoryId,
        User $user,
    ): GradingCategory {
        $this->assertRecordEditable($record, $user);
        $quarter = $this->quarter($record, $quarterNumber);
        $option = $this->effectiveOption($record, $quarter);
        $category = $option?->leafCategories()->firstWhere('id', $categoryId);

        $this->failUnless($category, 'The selected assessment category is not part of this quarter\'s grading option.');
        $this->failUnless(
            $category->canEditOn($record, $user),
            "You do not have edit access to \"{$category->name}\" on this class record.",
        );

        return $category;
    }

    private function matchingTargetCategory(
        ClassRecord $target,
        int $quarterNumber,
        GradingCategory $sourceCategory,
        User $user,
    ): GradingCategory {
        $quarter = $this->quarter($target, $quarterNumber);
        $option = $this->effectiveOption($target, $quarter);
        $category = $option?->leafCategories()->first(function (GradingCategory $candidate) use ($sourceCategory) {
            if (strcasecmp((string) $candidate->code, (string) $sourceCategory->code) !== 0) {
                return false;
            }

            return ! $sourceCategory->subject_id
                || (int) $candidate->subject_id === (int) $sourceCategory->subject_id;
        });

        $this->failUnless(
            $category,
            "Its Q{$quarterNumber} grading option has no matching {$sourceCategory->code} category.",
        );
        $this->failUnless(
            $category->canEditOn($target, $user),
            "You do not have edit access to \"{$category->name}\" on this class record.",
        );

        return $category;
    }

    private function createForRecord(
        ClassRecord $record,
        int $quarterNumber,
        GradingCategory $category,
        array $attributes,
        User $user,
    ): ClassRecordAssessment {
        $this->assertRecordEditable($record, $user);
        $quarter = $this->quarter($record, $quarterNumber);
        $this->failUnless(! $quarter->is_locked, "Quarter {$quarterNumber} is locked.");

        $dates = collect($attributes['activity_dates'] ?? [$attributes['activity_date']])
            ->push($attributes['activity_date'])
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->sort()
            ->values();
        $date = $dates->first();
        $isAdmin = $user->hasPermission('class-records.admin');

        if (! $isAdmin) {
            foreach ($dates as $occurrenceDate) {
                if (WatRuleService::violatesPlottingDeadline($occurrenceDate)) {
                    $deadline = WatRuleService::plottingDeadline($occurrenceDate)->format('D, M d, Y \a\t 12:00 NN');
                    throw ValidationException::withMessages([
                        'activity_dates' => "{$occurrenceDate} missed the plotting deadline of {$deadline}. Same-week plotting is not allowed.",
                    ]);
                }
            }
        }

        return DB::transaction(function () use (
            $record,
            $quarter,
            $quarterNumber,
            $category,
            $attributes,
            $date,
            $dates,
            $isAdmin
        ) {
            // Serialize category numbering on this quarter so two calendar
            // clicks cannot claim the same (category, assessment_number).
            ClassRecordQuarter::whereKey($quarter->id)->lockForUpdate()->firstOrFail();
            $existing = ClassRecordAssessment::where('class_record_quarter_id', $quarter->id)
                ->lockForUpdate()
                ->get();
            $nextNumber = ((int) $existing
                ->where('grading_category_id', $category->id)
                ->max('assessment_number')) + 1;

            $assessmentType = WatRuleService::deriveType($category->code, $nextNumber);
            $isGraded = array_key_exists('is_graded', $attributes)
                ? (bool) $attributes['is_graded']
                : true;
            $isMajor = WatRuleService::isMajor($assessmentType, $category);
            if ($isGraded && $record->section?->levelid !== null) {
                $grade = (int) $record->section->levelid;
                $countedDates = $dates->reject(fn ($occurrenceDate) => WatRuleService::isExamExempt(
                    $assessmentType,
                    $record->school_year_id,
                    $quarterNumber,
                    $occurrenceDate,
                ));
                $candidateOccurrences = $countedDates->map(fn ($occurrenceDate) => [
                    'assessment_key' => 'new',
                    'activity_date' => $occurrenceDate,
                    'section_id' => $record->section_id,
                    'subject_id' => $record->subject_id,
                    'subject_type' => $record->subject?->subject_type,
                    'is_graded' => true,
                    'is_major' => $isMajor,
                ]);

                foreach ($countedDates as $occurrenceDate) {
                    $day = WatRuleService::gradeCountsOnDate(
                        $record->section_id,
                        $grade,
                        $record->school_year_id,
                        $occurrenceDate,
                        [],
                        $candidateOccurrences->where('activity_date', $occurrenceDate)->values()->all(),
                    );
                    $this->failUnless(
                        $day['graded'] <= WatRuleService::DAILY_GRADED_MAX,
                        "{$occurrenceDate} already has the maximum number of graded assessments.",
                    );
                    $this->failUnless(
                        ! $isMajor || $day['major'] <= WatRuleService::DAILY_MAJOR_MAX,
                        "{$occurrenceDate} already has the maximum number of major assessments.",
                    );
                }

                foreach ($countedDates->groupBy(fn ($occurrenceDate) => Carbon::parse($occurrenceDate)
                    ->startOfWeek(Carbon::MONDAY)->toDateString()) as $weekStart => $weekDates) {
                    $week = WatRuleService::gradeCountsInWeek(
                        $record->section_id,
                        $grade,
                        $record->school_year_id,
                        $weekStart,
                        [],
                        $candidateOccurrences->filter(
                            fn ($candidate) => Carbon::parse($candidate['activity_date'])
                                ->startOfWeek(Carbon::MONDAY)->toDateString() === $weekStart
                        )->values()->all(),
                    );
                    $this->failUnless(
                        $week['graded'] <= WatRuleService::WEEKLY_GRADED_MAX,
                        "The week containing {$weekDates->first()} already has the maximum number of graded assessments.",
                    );
                    $this->failUnless(
                        ! $isMajor || $week['major'] <= WatRuleService::WEEKLY_MAJOR_MAX,
                        "The week containing {$weekDates->first()} already has the maximum number of major assessments.",
                    );
                }
            }

            if (! $isAdmin) {
                foreach ($dates as $occurrenceDate) {
                    if (WatRuleService::isExamExempt(
                        $assessmentType,
                        $record->school_year_id,
                        $quarterNumber,
                        $occurrenceDate,
                    )) {
                        continue;
                    }
                    $meets = WatRuleService::meetsOnDate(
                        $record->subject_id,
                        $record->section_id,
                        $record->school_year_id,
                        $occurrenceDate,
                    );
                    $this->failUnless(
                        $meets !== false,
                        "{$record->subject_name} has no scheduled class with this section on "
                            .Carbon::parse($occurrenceDate)->format('l, M d').'.',
                    );
                }
            }

            $nextSort = ((int) $existing->max('sort_order')) + 1;

            $assessment = ClassRecordAssessment::create([
                'class_record_quarter_id' => $quarter->id,
                'grading_category_id' => $category->id,
                'assessment_type' => $assessmentType,
                'is_graded' => $isGraded,
                'is_major' => $isMajor,
                'assessment_number' => $nextNumber,
                'title' => $attributes['title'],
                'activity_date' => $date,
                'plotted_at' => now(),
                'max_score' => $isGraded ? (float) $attributes['max_score'] : 0,
                'sort_order' => $nextSort,
            ]);
            $assessment->syncActivityDates($dates->all());

            return $assessment;
        }, 3);
    }

    private function assertSameSubjectTarget(ClassRecord $source, ?ClassRecord $target, User $user): void
    {
        $this->failUnless($target, 'That class record no longer exists.');
        $this->assertRecordEditable($target, $user);
        $this->failUnless(
            (int) $source->section_id !== (int) $target->section_id,
            'Select a class record from another section.',
        );

        $sameSubject = $source->subject_id && $target->subject_id
            ? (int) $source->subject_id === (int) $target->subject_id
            : strcasecmp((string) $source->subject_name, (string) $target->subject_name) === 0;

        $this->failUnless($sameSubject, 'Different subject.');
    }

    private function assertRecordEditable(ClassRecord $record, User $user): void
    {
        $this->failUnless($record->canEdit($user), 'You do not have edit access to that class record.');
        $this->failUnless(! $record->isArchived(), 'That class record has been archived.');
        $this->failUnless(
            $record->isCurrentSchoolYear(),
            'That class record is from a past school year and is read-only.',
        );
    }

    private function quarter(ClassRecord $record, int $quarterNumber): ClassRecordQuarter
    {
        $this->failUnless(in_array($quarterNumber, [1, 2, 3, 4], true), 'Quarter must be 1-4.');

        return ClassRecordQuarter::firstOrCreate(
            ['class_record_id' => $record->id, 'quarter' => $quarterNumber],
            ['is_locked' => false],
        );
    }

    private function effectiveOption(ClassRecord $record, ClassRecordQuarter $quarter): ?GradingOption
    {
        return GradingOption::with('categories')
            ->find($quarter->grading_option_id ?? $record->grading_option_id);
    }

    private function resultRow(ClassRecord $record, ClassRecordAssessment $assessment): array
    {
        return [
            'class_record_id' => $record->id,
            'label' => $record->year_level_section,
            'assessment_id' => $assessment->id,
        ];
    }

    private function failUnless(mixed $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['assessment' => $message]);
        }
    }
}
