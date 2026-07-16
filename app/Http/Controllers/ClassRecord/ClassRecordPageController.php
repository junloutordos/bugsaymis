<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\GradingOption;
use App\Models\ClassRecord\StanineLookup;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ClassRecordPageController extends Controller
{
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

        $records = $query->get();

        $currentSY = SchoolYear::where('is_current', true)->first();

        return Inertia::render('ClassRecord/Index', [
            'classRecords'      => $records,
            'gradingOptions'    => GradingOption::with('categories')->where('is_active', true)->orderBy('id')->get(),
            'isAdmin'           => $this->isAdmin(),
            'filters'           => $request->only(['school_year']),
            'currentSchoolYear' => $currentSY ? $currentSY->name : null,
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
            'subject:id,name,subject_type,grade_level',
            'gradingOption.categories',
            'quarters.assessments.gradingCategory',
            'quarters.students',
        ]);

        $currentSY   = SchoolYear::where('is_current', true)->first();
        $isCurrentSY = $currentSY && $classRecord->school_year_id === $currentSY->id;

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
            'classRecord'        => $classRecord,
            'isAdmin'            => $this->isAdmin(),
            'stanineLookup'      => StanineLookup::orderByDesc('percentage')->get(['percentage', 'grade_equivalent', 'adjectival_equivalent']),
            'isCurrentSY'        => $isCurrentSY,
            'currentSYName'      => $currentSY?->name,
            'sameSubjectRecords' => $sameSubjectRecords,
            'scheduledDays'      => $scheduledDays,
            'quizzes'            => \App\Models\Quiz\Quiz::where('source_type', 'class_record')
                ->where('source_id', $classRecord->id)
                ->withCount('questions')
                ->get()
                ->map(fn ($q) => ['id' => $q->id, 'title' => $q->title, 'status' => $q->status, 'question_count' => $q->questions_count]),
        ]);
    }
}
