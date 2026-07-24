<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\GradingOption;
use App\Models\ClassRecord\StanineLookup;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Quiz\Quiz;
use App\Services\ClassRecord\GradingOptionScopeService;
use App\Services\DigitalSignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ClassRecordPageController extends Controller
{
    public function __construct(private readonly GradingOptionScopeService $optionScope)
    {
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
            $query->where('teacher_id', Auth::id());
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
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);

        $classRecord->load([
            'teacher:id,name,position',
            'subject:id,name,subject_type,grade_level,academic_unit_id',
            'subject.academicUnit:id,code',
            'section:id,levelid,sectionname',
            'gradingOption.categories',
            'quarters.gradingOption.categories',
            'quarters.assessments.gradingCategory',
            'quarters.students',
        ]);

        // Expose the grading option in force per quarter (per-quarter override,
        // else the record default) so the UI can show/switch it per quarter.
        $defaultOptionId = $classRecord->grading_option_id;
        $classRecord->quarters->each(function ($qtr) use ($defaultOptionId) {
            $qtr->setAttribute('effective_grading_option_id', $qtr->grading_option_id ?? $defaultOptionId);
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

        // Other class records by the same teacher for the same subject (for copy-from-section feature)
        $sameSubjectRecords = ClassRecord::where('teacher_id', $classRecord->teacher_id)
            ->where('id', '!=', $classRecord->id)
            ->whereRaw('LOWER(subject_name) = LOWER(?)', [$classRecord->subject_name])
            ->orderByDesc('school_year')
            ->get(['id', 'subject_name', 'year_level_section', 'school_year']);

        return Inertia::render('ClassRecord/Show', [
            'classRecord' => $classRecord,
            'isAdmin' => $this->isAdmin(),
            'gradingOptions' => $classRecord->subject
                ? $this->optionScope->selectableForSubject($classRecord->subject)
                : GradingOption::with('categories')
                    ->whereNull('owner_designation_id')
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->get(),
            'stanineLookup' => StanineLookup::orderByDesc('percentage')->get(['percentage', 'grade_equivalent', 'adjectival_equivalent']),
            'isCurrentSY' => $isCurrentSY,
            'currentSYName' => $currentSY?->name,
            'sameSubjectRecords' => $sameSubjectRecords,
            'scheduledDays' => $scheduledDays,
            'quizzes' => Quiz::where('source_type', 'class_record')
                ->where('source_id', $classRecord->id)
                ->withCount('questions')
                ->get()
                ->map(fn ($q) => ['id' => $q->id, 'title' => $q->title, 'status' => $q->status, 'question_count' => $q->questions_count]),
        ]);
    }
}
