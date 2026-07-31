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

        $date = Carbon::parse($attributes['activity_date'])->toDateString();
        $isAdmin = $user->hasPermission('class-records.admin');

        if (! $isAdmin && WatRuleService::violatesPlottingDeadline($date)) {
            $deadline = WatRuleService::plottingDeadline($date)->format('D, M d, Y \a\t 12:00 NN');
            throw ValidationException::withMessages([
                'activity_date' => "The plotting deadline for this week was {$deadline}. Same-week plotting is not allowed.",
            ]);
        }

        return DB::transaction(function () use (
            $record,
            $quarter,
            $quarterNumber,
            $category,
            $attributes,
            $date,
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
            $isExamExempt = WatRuleService::isExamExempt(
                $assessmentType,
                $record->school_year_id,
                $quarterNumber,
                $date,
            );

            if ($isGraded && ! $isExamExempt && $record->section?->levelid !== null) {
                $grade = (int) $record->section->levelid;
                $day = WatRuleService::gradeCountsOnDate(
                    $record->section_id,
                    $grade,
                    $record->school_year_id,
                    $date,
                );
                $week = WatRuleService::gradeCountsInWeek(
                    $record->section_id,
                    $grade,
                    $record->school_year_id,
                    $date,
                );

                $this->failUnless(
                    $day['graded'] + 1 <= WatRuleService::DAILY_GRADED_MAX,
                    'This section already has the maximum number of graded assessments for that day.',
                );
                $this->failUnless(
                    ! $isMajor || $day['major'] + 1 <= WatRuleService::DAILY_MAJOR_MAX,
                    'This section already has the maximum number of major assessments for that day.',
                );
                $this->failUnless(
                    $week['graded'] + 1 <= WatRuleService::WEEKLY_GRADED_MAX,
                    'This section already has the maximum number of graded assessments for that week.',
                );
                $this->failUnless(
                    ! $isMajor || $week['major'] + 1 <= WatRuleService::WEEKLY_MAJOR_MAX,
                    'This section already has the maximum number of major assessments for that week.',
                );
            }

            if (! $isAdmin && ! $isExamExempt) {
                $meets = WatRuleService::meetsOnDate(
                    $record->subject_id,
                    $record->section_id,
                    $record->school_year_id,
                    $date,
                );
                $this->failUnless(
                    $meets !== false,
                    "{$record->subject_name} has no scheduled class with this section on "
                        .Carbon::parse($date)->format('l, M d').'.',
                );
            }

            $nextSort = ((int) $existing->max('sort_order')) + 1;

            return ClassRecordAssessment::create([
                'class_record_quarter_id' => $quarter->id,
                'grading_category_id' => $category->id,
                'assessment_type' => $assessmentType,
                'is_graded' => $isGraded,
                'is_major' => $isMajor,
                'assessment_number' => $nextNumber,
                'title' => $attributes['title'],
                'activity_date' => $date,
                'plotted_at' => now(),
                'max_score' => $attributes['max_score'],
                'sort_order' => $nextSort,
            ]);
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
