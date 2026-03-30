<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AiScheduleJob;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Services\FacultyLoading\LoadBalancingService;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AiDashboardController extends Controller
{
    public function __construct(private readonly LoadBalancingService $balancer) {}

    public function index(): Response
    {
        $this->authorize('faculty_loading.manage');

        // Current term (for auto-populating analysis)
        $currentTerm = AcademicTerm::where('is_current', true)
            ->with('schoolYear')
            ->first();

        // Load balance summary for current term (graceful on empty data)
        $loadSummary = null;
        if ($currentTerm) {
            try {
                $analysis    = $this->balancer->analyse($currentTerm->id);
                $loadSummary = $analysis['summary'];
                $loadSummary['term_label'] = $analysis['term']['full_label'];
                $loadSummary['faculty_profiles'] = collect($analysis['faculty'])
                    ->map(fn ($f) => [
                        'faculty_name' => $f['faculty_name'],
                        'total_units'  => $f['total_units'],
                        'load_status'  => $f['load_status'],
                        'flags_count'  => count($f['flags']),
                    ])
                    ->sortByDesc('total_units')
                    ->values()
                    ->all();
            } catch (Throwable) {
                // No faculty loads yet — summary stays null
            }
        }

        // Recent AI schedule generation jobs
        $recentJobs = AiScheduleJob::with('academicTerm', 'createdBy:id,name')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($j) => [
                'id'                  => $j->id,
                'academic_term_label' => $j->academicTerm?->name ?? '—',
                'status'              => $j->status,
                'hard_conflicts'      => $j->hard_conflicts,
                'schedules_generated' => $j->schedules_generated,
                'fitness_score'       => $j->fitness_score,
                'duration_seconds'    => $j->duration_seconds,
                'created_by_name'     => $j->createdBy?->name ?? '—',
                'created_at'          => $j->created_at?->toISOString(),
            ]);

        // School years for selectors on child pages
        $schoolYears = SchoolYear::with('terms')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($sy) => [
                'id'         => $sy->id,
                'name'       => $sy->name,
                'is_current' => $sy->is_current,
                'terms'      => $sy->terms->map(fn ($t) => [
                    'id'         => $t->id,
                    'name'       => $t->name,
                    'is_current' => $t->is_current,
                ])->values(),
            ]);

        return Inertia::render('FacultyLoading/AiDashboard/Index', [
            'currentTerm' => $currentTerm ? [
                'id'   => $currentTerm->id,
                'name' => $currentTerm->name,
                'full_label' => $currentTerm->name . ', S.Y. ' . ($currentTerm->schoolYear?->name ?? '—'),
            ] : null,
            'loadSummary'   => $loadSummary,
            'recentJobs'    => $recentJobs,
            'schoolYears'   => $schoolYears,
        ]);
    }
}
