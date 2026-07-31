<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\GradingOption;
use App\Models\ClassRecord\StanineLookup;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Quiz\Quiz;
use App\Services\ClassRecord\ClassRecordMonitorScopeService;
use App\Services\ClassRecord\GradingOptionScopeService;
use App\Services\ClassRecord\WatRuleService;
use App\Services\DigitalSignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ClassRecordPageController extends Controller
{
    public function __construct(
        private readonly GradingOptionScopeService $optionScope,
        private readonly ClassRecordMonitorScopeService $monitorScope,
    ) {
    }

    private function isAdmin(): bool
    {
        return Auth::user()->hasPermission('class-records.admin');
    }

    /**
     * GET /class-records-page  → renders the list Inertia page
     */
    public function index(Request $request)
    {
        $query = ClassRecord::with(['teacher:id,name', 'gradingOption:id,name', 'quarters'])
            ->orderByDesc('updated_at');

        if (! $this->isAdmin()) {
            $userId = Auth::id();
            $query->where(function ($q) use ($userId) {
                $q->where('teacher_id', $userId)
                    ->orWhereHas('coTeachers', fn ($ctq) => $ctq->where('user_id', $userId));
            });
        }

        if ($request->filled('school_year')) {
            $query->where('school_year', $request->query('school_year'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        } else {
            // Active listing hides archived (soft-deleted) records; they are
            // reachable via the explicit ?status=archived filter for recovery.
            $query->where('status', '<>', 'archived');
        }

        $records = $query->get();

        $currentSY = SchoolYear::where('is_current', true)->first();
        $user = Auth::user();
        $isAdmin = $this->isAdmin();

        // Admins may assign any AUH's unit when creating an option; AUHs are
        // restricted to the unit(s) their own current-term designation covers.
        $unitDesignations = $isAdmin
            ? $this->optionScope->allAuhDesignations()
            : $this->optionScope->managedDesignations($user);

        return Inertia::render('ClassRecord/Index', [
            'classRecords' => $records,
            'gradingOptions' => $this->optionScope->selectableForUser($user),
            'managedGradingOptions' => $this->optionScope->manageableFor($user),
            'gradingOptionUnits' => $unitDesignations
                ->map(fn ($designation) => [
                    'id' => $designation->id,
                    'code' => $designation->code,
                    'name' => preg_replace('/^Academic Unit Head\s*-\s*/i', '', $designation->name),
                ])
                ->values(),
            // Subjects that share a class record across teachers (PE/Health/
            // Music) — offered as the per-leaf subject picker when a manager
            // is setting up a shared grading option's categories.
            'sharedGroupSubjects' => \App\Models\FacultyLoading\Subject::whereNotNull('subject_group')
                ->orderBy('grade_level')->orderBy('name')
                ->get(['id', 'name', 'subject_group', 'grade_level']),
            'isAdmin' => $isAdmin,
            'canManageGradingOptions' => $this->optionScope->canManage($user),
            'filters' => $request->only(['school_year', 'status']),
            'currentSchoolYear' => $currentSY ? $currentSY->name : null,
            'hasPin' => ! empty($user->signature_pin),
            'signatureUri' => app(DigitalSignatureService::class)->getSignatureDataUri($user),
        ]);
    }

    /**
     * GET /class-records-page/{id}  → renders the detail Inertia page
     */
    public function show(ClassRecord $classRecord)
    {
        $user = Auth::user();
        $isOwner = $classRecord->canEdit($user);
        $isMonitorView = ! $this->isAdmin() && ! $isOwner && $this->monitorScope->canView($user, $classRecord);
        abort_unless($isOwner || $isMonitorView, 403);

        $classRecord->load([
            'teacher:id,name,position',
            'coTeachers.subject:id,name',
            'coTeachers.user:id,name,position',
            'subject:id,name,subject_type,grade_level,academic_unit_id,has_ilp',
            'subject.academicUnit:id,code',
            'section:id,levelid,sectionname',
            'gradingOption.categories',
            'quarters.gradingOption.categories',
            'quarters.assessments.dates',
            'quarters.assessments.gradingCategory',
            'quarters.assessments.pendingDeletionRequest',
            'quarters.students',
        ]);

        // Expose the grading option in force per quarter (per-quarter override,
        // else the record default) so the UI can show/switch it per quarter.
        $defaultOptionId = $classRecord->grading_option_id;
        $classRecord->quarters->each(function ($qtr) use ($defaultOptionId) {
            $qtr->setAttribute('effective_grading_option_id', $qtr->grading_option_id ?? $defaultOptionId);
            $qtr->assessments->each(fn ($assessment) => $assessment->setAttribute(
                'activity_dates',
                $assessment->activityDateStrings()->all()
            ));
        });

        $currentSY = SchoolYear::where('is_current', true)->first();
        $isCurrentSY = $currentSY && $classRecord->school_year_id === $currentSY->id;

        $classRecord->setAttribute('can_change_grading_option', $classRecord->canChangeGradingOption($this->isAdmin()));

        // Weekdays this subject meets this section per the class schedule —
        // drives the Setup datepicker's disabled days. Empty = no schedule
        // plotted yet, so the picker disables nothing (fail-open, mirrors
        // WatRuleService::meetsOnDate()).
        $scheduledDays = [];
        if ($classRecord->subject_id && $classRecord->section_id) {
            $scheduledDays = ClassSchedule::where('section_id', $classRecord->section_id)
                ->where('subject_id', $classRecord->subject_id)
                ->where('school_year_id', $classRecord->school_year_id)
                ->distinct()
                ->pluck('day_of_week')
                ->values()
                ->all();
        }

        // Other class records by the same teacher for the same subject (for copy-from-section feature).
        // Archived records are excluded — they're soft-deleted and must never resurface as a copy source.
        $sameSubjectRecords = ClassRecord::with([
                'coTeachers',
                'gradingOption.categories',
                'quarters.gradingOption.categories',
                'quarters.assessments:id,class_record_quarter_id,grading_category_id',
            ])
            ->where('id', '!=', $classRecord->id)
            ->where('status', '<>', 'archived')
            ->when(
                $classRecord->subject_id,
                fn ($q) => $q->where('subject_id', $classRecord->subject_id),
                fn ($q) => $q->whereRaw('LOWER(subject_name) = LOWER(?)', [$classRecord->subject_name]),
            )
            ->orderByDesc('school_year')
            ->get(['id', 'teacher_id', 'subject_id', 'section_id', 'grading_option_id', 'school_year_id', 'subject_name', 'year_level_section', 'school_year'])
            ->filter(fn (ClassRecord $record) => $record->canEdit($user)
                && (int) $record->section_id !== (int) $classRecord->section_id)
            ->values();

        $isAdmin = $this->isAdmin();
        $isCategoryScoped = ! $isAdmin && $classRecord->coTeachers->isNotEmpty();
        $sourceQuarters = $classRecord->quarters->keyBy('quarter');
        $sourceOwnedCategoryIds = collect(range(1, 4))->mapWithKeys(function (int $number) use ($classRecord, $user) {
            $quarter = $classRecord->quarters->firstWhere('quarter', $number);
            $option = $quarter?->gradingOption ?? $classRecord->gradingOption;

            return [$number => $option?->leafCategories()
                ->filter(fn ($category) => $classRecord->canEdit($user, $category->subject_id))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all() ?? []];
        });

        $sameSubjectRecords->each(function (ClassRecord $record) use (
            $user,
            $isAdmin,
            $isCategoryScoped,
            $sourceQuarters,
            $sourceOwnedCategoryIds
        ) {
            $record->setAttribute('apply_quarters', collect(range(1, 4))->map(function (int $number) use (
                $record,
                $user,
                $isAdmin,
                $isCategoryScoped,
                $sourceQuarters,
                $sourceOwnedCategoryIds
            ) {
                $quarter = $record->quarters->firstWhere('quarter', $number);
                $option = $quarter?->gradingOption ?? $record->gradingOption;
                $assessments = $quarter?->assessments ?? collect();
                $editableCategoryIds = $option?->leafCategories()
                    ->filter(fn ($category) => $record->canEdit($user, $category->subject_id))
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all() ?? [];

                $sourceAssessments = $sourceQuarters->get($number)?->assessments ?? collect();
                if ($isCategoryScoped) {
                    $ownedIds = $sourceOwnedCategoryIds->get($number, []);
                    $sourceAssessments = $sourceAssessments->filter(
                        fn ($assessment) => in_array((int) $assessment->grading_category_id, $ownedIds, true)
                    );
                }

                $scheduleConflicts = [];
                if (! $isAdmin) {
                    foreach ($sourceAssessments as $assessment) {
                        foreach ($assessment->activityDateStrings() as $date) {
                            if (WatRuleService::isExamExempt(
                                $assessment->assessment_type,
                                $record->school_year_id,
                                $number,
                                $date,
                            )) {
                                continue;
                            }
                            if (WatRuleService::meetsOnDate(
                                $record->subject_id,
                                $record->section_id,
                                $record->school_year_id,
                                $date,
                            ) === false) {
                                $scheduleConflicts[] = $date;
                            }
                        }
                    }
                }

                return [
                    'number' => $number,
                    'is_locked' => (bool) ($quarter?->is_locked ?? false),
                    'effective_grading_option_id' => $quarter?->grading_option_id ?? $record->grading_option_id,
                    'assessment_count' => $assessments->count(),
                    'assessment_category_ids' => $assessments->pluck('grading_category_id')
                        ->map(fn ($id) => (int) $id)->unique()->values()->all(),
                    'editable_category_ids' => $editableCategoryIds,
                    'schedule_conflicts' => array_values(array_unique($scheduleConflicts)),
                ];
            })->values()->all());
        });

        // Sibling records splitting this exact subject+section+teacher+SY into
        // separate categories (e.g. STEM Research "Ongoing" vs "Completed") —
        // used by the roster tab's Transfer action.
        $siblings = $classRecord->siblingsQuery()->get(['id', 'subject_name', 'category_label']);

        return Inertia::render('ClassRecord/Show', [
            'classRecord' => $classRecord,
            'isAdmin' => $this->isAdmin(),
            'isMonitorView' => $isMonitorView,
            'gradingOptions' => $classRecord->subject
                ? $this->optionScope->selectableForSubject($classRecord->subject, Auth::user())
                : GradingOption::with('categories')
                    ->whereNull('owner_designation_id')
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->get(),
            'stanineLookup' => StanineLookup::orderByDesc('percentage')->get(['percentage', 'grade_equivalent', 'adjectival_equivalent']),
            'isCurrentSY' => $isCurrentSY,
            'currentSYName' => $currentSY?->name,
            'sameSubjectRecords' => $sameSubjectRecords,
            'siblings' => $siblings,
            'scheduledDays' => $scheduledDays,
            'quizzes' => Quiz::where('source_type', 'class_record')
                ->where('source_id', $classRecord->id)
                ->withCount('questions')
                ->get()
                ->map(fn ($q) => ['id' => $q->id, 'title' => $q->title, 'status' => $q->status, 'question_count' => $q->questions_count]),
        ]);
    }
}
