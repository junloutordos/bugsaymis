<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\JobItem;
use App\Models\JobVacancy;
use App\Models\Placement;
use App\Models\RecruitmentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RecruitmentReportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('recruitment.view');

        $year = $request->integer('year', now()->year);

        // ── 1. Plantilla Utilization ────────────────────────────────────────
        // For Plantilla types, count individual plantilla item numbers (one job
        // item can represent several vacancies). Other types have no plantilla
        // item numbers, so fall back to a job-item-level count.
        $plantillaTypeIds = RecruitmentType::whereIn('name', ['Plantilla Teaching', 'Plantilla Non-Teaching'])->pluck('id');

        $plantillaStats = DB::table('job_item_plantilla_numbers')
            ->join('job_items', 'job_item_plantilla_numbers.job_item_id', '=', 'job_items.id')
            ->whereNull('job_items.deleted_at')
            ->select(
                'job_items.recruitment_type_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN job_item_plantilla_numbers.status = 'filled' THEN 1 ELSE 0 END) as filled")
            )
            ->groupBy('job_items.recruitment_type_id')
            ->get()
            ->keyBy('recruitment_type_id');

        $utilization = RecruitmentType::withCount([
            'jobItems as total_items',
            'jobItems as open_items' => fn ($q) => $q->where('status', 'approved'),
        ])->get()->map(function ($t) use ($plantillaStats, $plantillaTypeIds) {
            if ($plantillaTypeIds->contains($t->id) && $stat = $plantillaStats->get($t->id)) {
                $total  = (int) $stat->total;
                $filled = (int) $stat->filled;
            } else {
                $total  = $t->total_items;
                $filled = 0;
            }

            return [
                'name'      => $t->name,
                'total'     => $total,
                'filled'    => $filled,
                'open'      => $t->open_items,
                'fill_rate' => $total > 0 ? round(($filled / $total) * 100, 1) : 0,
            ];
        });

        // ── 2. Vacancy Fill Rate by Type ────────────────────────────────────
        $fillRate = JobVacancy::select('job_items.recruitment_type_id')
            ->join('job_items', 'job_vacancies.job_item_id', '=', 'job_items.id')
            ->selectRaw('COUNT(*) as total_vacancies')
            ->selectRaw("SUM(CASE WHEN job_vacancies.status = 'filled' THEN 1 ELSE 0 END) as filled_count")
            ->whereYear('job_vacancies.posting_date', $year)
            ->groupBy('job_items.recruitment_type_id')
            ->with('jobItem') // won't work here, use join approach
            ->get();

        // Simpler approach using raw join for display name
        $fillRateData = DB::table('job_vacancies')
            ->join('job_items', 'job_vacancies.job_item_id', '=', 'job_items.id')
            ->join('recruitment_types', 'job_items.recruitment_type_id', '=', 'recruitment_types.id')
            ->select(
                'recruitment_types.name as type_name',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN job_vacancies.status = 'filled' THEN 1 ELSE 0 END) as filled"),
                DB::raw("SUM(CASE WHEN job_vacancies.status = 'open' THEN 1 ELSE 0 END) as open_count")
            )
            ->whereYear('job_vacancies.posting_date', $year)
            ->whereNull('job_vacancies.deleted_at')
            ->whereNull('job_items.deleted_at')
            ->groupBy('recruitment_types.id', 'recruitment_types.name')
            ->get()
            ->map(fn ($r) => [
                'type'       => $r->type_name,
                'total'      => $r->total,
                'filled'     => $r->filled,
                'open'       => $r->open_count,
                'fill_rate'  => $r->total > 0 ? round(($r->filled / $r->total) * 100, 1) : 0,
            ]);

        // ── 3. Time-to-Hire (submitted → placement, in days) ───────────────
        $timeToHire = DB::table('applications')
            ->join('placements', 'applications.id', '=', 'placements.application_id')
            ->join('job_vacancies', 'applications.job_vacancy_id', '=', 'job_vacancies.id')
            ->join('job_items', 'job_vacancies.job_item_id', '=', 'job_items.id')
            ->join('recruitment_types', 'job_items.recruitment_type_id', '=', 'recruitment_types.id')
            ->select(
                'recruitment_types.name as type_name',
                DB::raw('COUNT(*) as placements'),
                DB::raw('AVG(DATEDIFF(placements.created_at, applications.application_date)) as avg_days'),
                DB::raw('MIN(DATEDIFF(placements.created_at, applications.application_date)) as min_days'),
                DB::raw('MAX(DATEDIFF(placements.created_at, applications.application_date)) as max_days')
            )
            ->whereYear('placements.created_at', $year)
            ->whereNull('applications.deleted_at')
            ->groupBy('recruitment_types.id', 'recruitment_types.name')
            ->get()
            ->map(fn ($r) => [
                'type'       => $r->type_name,
                'placements' => $r->placements,
                'avg_days'   => round($r->avg_days ?? 0, 1),
                'min_days'   => $r->min_days ?? 0,
                'max_days'   => $r->max_days ?? 0,
            ]);

        // ── 4. Monthly Application Pipeline (current year) ─────────────────
        $pipeline = DB::table('applications')
            ->select(
                DB::raw('MONTH(application_date) as month'),
                DB::raw('COUNT(*) as submitted'),
                DB::raw("SUM(CASE WHEN current_stage = 'placement' THEN 1 ELSE 0 END) as placed"),
                DB::raw("SUM(CASE WHEN current_stage = 'rejected'  THEN 1 ELSE 0 END) as rejected"),
                DB::raw("SUM(CASE WHEN current_stage = 'withdrawn' THEN 1 ELSE 0 END) as withdrawn")
            )
            ->whereYear('application_date', $year)
            ->whereNull('deleted_at')
            ->groupBy(DB::raw('MONTH(application_date)'))
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $months = collect(range(1, 12))->map(fn ($m) => [
            'month'     => date('M', mktime(0, 0, 0, $m, 1)),
            'submitted' => $pipeline[$m]->submitted ?? 0,
            'placed'    => $pipeline[$m]->placed    ?? 0,
            'rejected'  => $pipeline[$m]->rejected  ?? 0,
            'withdrawn' => $pipeline[$m]->withdrawn ?? 0,
        ]);

        // ── 5. Summary KPIs ─────────────────────────────────────────────────
        $kpis = [
            'total_applicants'   => DB::table('applicants')->whereNull('deleted_at')->count(),
            'total_applications' => Application::whereYear('application_date', $year)->count(),
            'total_placements'   => Placement::whereYear('created_at', $year)->count(),
            'active_vacancies'   => JobVacancy::where('status', 'open')
                                        ->where('closing_date', '>=', now()->toDateString())
                                        ->count(),
            'open_positions'     => JobItem::whereIn('status', ['approved', 'published'])->count(),
        ];

        return Inertia::render('Recruitment/Reports/Index', [
            'year'         => $year,
            'years'        => range(now()->year, max(now()->year - 4, 2020)),
            'kpis'         => $kpis,
            'utilization'  => $utilization,
            'fill_rate'    => $fillRateData,
            'time_to_hire' => $timeToHire,
            'pipeline'     => $months,
        ]);
    }
}
