<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\GradingOption;
use App\Models\ClassRecord\StanineLookup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ClassRecordPageController extends Controller
{
    private function isAdmin(): bool
    {
        return Auth::user()->hasAnyRole(['Administrator', 'AUH', 'CID Chief']);
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

        return Inertia::render('ClassRecord/Index', [
            'classRecords'   => $records,
            'gradingOptions' => GradingOption::with('categories')->where('is_active', true)->orderBy('id')->get(),
            'isAdmin'        => $this->isAdmin(),
            'filters'        => $request->only(['school_year']),
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
            'gradingOption.categories',
            'quarters.assessments.gradingCategory',
            'quarters.students',
        ]);

        return Inertia::render('ClassRecord/Show', [
            'classRecord'   => $classRecord,
            'isAdmin'       => $this->isAdmin(),
            'stanineLookup' => StanineLookup::orderByDesc('percentage')->get(['percentage', 'grade_equivalent', 'adjectival_equivalent']),
        ]);
    }
}
