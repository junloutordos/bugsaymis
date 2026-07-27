<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Services\ClassRecord\ClassRecordMonitorScopeService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only monitoring dashboard for CID Chief and Academic Unit Heads —
 * answers "who has created a class record this SY, and who hasn't yet."
 * Never exposes a write path; every class-record mutation still lives behind
 * class-records.admin or record ownership, unaffected by this controller.
 */
class ClassRecordMonitorController extends Controller
{
    public function __construct(private readonly ClassRecordMonitorScopeService $monitorScope)
    {
    }

    public function index(): Response
    {
        abort_unless($this->monitorScope->canMonitor(Auth::user()), 403);

        $currentSY = SchoolYear::where('is_current', true)->first(['id', 'name']);

        if (! $currentSY) {
            return Inertia::render('ClassRecord/Monitor/Index', [
                'schoolYear' => null,
                'stats' => $this->emptyStats(),
                'rows' => [],
                'isCidChief' => $this->monitorScope->isCidChiefOrAdmin(Auth::user()),
            ]);
        }

        $facultyIds = $this->monitorScope->facultyIdsInScope(Auth::user());

        $assignments = LoadAssignment::with([
            'subject:id,name,subject_type,grade_level',
            'section:id,levelid,sectionname',
            'faculty:id,name',
        ])
            ->where('school_year_id', $currentSY->id)
            ->where('assignment_type', 'teaching')
            ->whereNotNull('subject_id')
            ->when($facultyIds !== null, fn ($q) => $q->whereIn('user_id', $facultyIds))
            ->get()
            // Same subject+section can be assigned across both terms of one SY —
            // collapse to the single card a monitor should see per pairing.
            ->unique(fn ($la) => "{$la->user_id}_{$la->subject_id}_{$la->section_id}");

        $recordsByPair = ClassRecord::where('school_year_id', $currentSY->id)
            ->where('status', '<>', 'archived')
            ->whereNotNull('subject_id')
            ->when($facultyIds !== null, fn ($q) => $q->whereIn('teacher_id', $facultyIds))
            ->get(['id', 'teacher_id', 'subject_id', 'section_id', 'status', 'checked_at', 'category_label'])
            ->groupBy(fn ($r) => "{$r->teacher_id}_{$r->subject_id}_{$r->section_id}");

        $rows = $assignments->map(function (LoadAssignment $la) use ($recordsByPair) {
            $key = "{$la->user_id}_{$la->subject_id}_{$la->section_id}";
            $records = $recordsByPair->get($key, collect());
            // A subject can be split into multiple category-labeled records
            // (e.g. STEM Research "Ongoing"/"Completed") — report the most
            // advanced status among them for this one summary row.
            $rank = ['draft' => 0, 'submitted' => 1, 'checked' => 2];
            $best = $records->sortByDesc(fn ($r) => $rank[$r->status] ?? -1)->first();

            $grade = (int) ($la->subject?->grade_level ?? 0);
            $sectionLabel = $la->section?->sectionname
                ?? ($grade === 0 ? 'Grades 11–12 — Cross-section' : "Grade {$grade} — Cross-section");

            return [
                'teacher_id' => $la->user_id,
                'teacher_name' => $la->faculty?->name,
                'subject_name' => $la->subject?->name,
                'section_label' => $sectionLabel,
                'status' => $best?->status ?? 'not_created',
                'class_record_id' => $best?->id,
                'record_count' => $records->count(),
            ];
        })->values();

        $stats = [
            'total_assignments' => $rows->count(),
            'created' => $rows->where('status', '!=', 'not_created')->count(),
            'missing' => $rows->where('status', 'not_created')->count(),
            'draft' => $rows->where('status', 'draft')->count(),
            'submitted' => $rows->where('status', 'submitted')->count(),
            'checked' => $rows->where('status', 'checked')->count(),
        ];

        return Inertia::render('ClassRecord/Monitor/Index', [
            'schoolYear' => ['id' => $currentSY->id, 'name' => $currentSY->name],
            'stats' => $stats,
            'rows' => $rows,
            'isCidChief' => $this->monitorScope->isCidChiefOrAdmin(Auth::user()),
        ]);
    }

    private function emptyStats(): array
    {
        return [
            'total_assignments' => 0,
            'created' => 0,
            'missing' => 0,
            'draft' => 0,
            'submitted' => 0,
            'checked' => 0,
        ];
    }
}
