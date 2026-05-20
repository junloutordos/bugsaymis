<?php

namespace App\Http\Controllers;

use App\Models\CsmResponse;
use App\Models\ITJobRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MISDashboardController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        [$year, $mon] = explode('-', $month);

        $y   = (int) $year;
        $m   = (int) $mon;
        $now = Carbon::now();

        // ── KPI Cards ─────────────────────────────────────────────────────────

        $totalThisMonth = DB::table('it_job_requests')
            ->whereYear('created_at', $y)->whereMonth('created_at', $m)
            ->count();

        $completedThisMonth = DB::table('it_job_requests')
            ->where('status', 'Request Completed')
            ->whereYear('completed_at', $y)->whereMonth('completed_at', $m)
            ->count();

        $pendingAction = DB::table('it_job_requests')
            ->where('status', 'Acted by MIS')->count();

        $avgRating = DB::table('it_job_requests')
            ->where('status', 'Request Completed')
            ->whereYear('completed_at', $y)->whereMonth('completed_at', $m)
            ->whereNotNull('rating')
            ->avg('rating');

        $avgResponseDays = DB::table('it_job_requests')
            ->where('status', 'Request Completed')
            ->whereYear('completed_at', $y)->whereMonth('completed_at', $m)
            ->whereNotNull('completed_at')
            ->selectRaw('ROUND(AVG(DATEDIFF(completed_at, created_at)), 1) as avg_days')
            ->value('avg_days');

        // ── Status Breakdown (donut) ──────────────────────────────────────────

        $statusBreakdown = DB::table('it_job_requests')
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderByDesc('count')
            ->pluck('count', 'status');

        // ── Category Breakdown (bar) ──────────────────────────────────────────

        $categoryBreakdown = DB::table('it_job_requests')
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('count')
            ->limit(12)
            ->get()
            ->map(fn ($r) => ['category' => $r->category, 'count' => (int) $r->count]);

        // ── Monthly Trend (last 12 months) ────────────────────────────────────

        $monthlyTrend = collect(range(11, 0))->map(function ($i) use ($now) {
            $d = $now->copy()->subMonths($i);
            return [
                'month'     => $d->format('M Y'),
                'filed'     => DB::table('it_job_requests')
                                   ->whereYear('created_at', $d->year)->whereMonth('created_at', $d->month)->count(),
                'completed' => DB::table('it_job_requests')
                                   ->where('status', 'Request Completed')
                                   ->whereYear('completed_at', $d->year)->whereMonth('completed_at', $d->month)->count(),
            ];
        })->values();

        // ── MIS Personnel Workload ─────────────────────────────────────────────
        // Group by users.id (not the free-text attendedby column) so each MIS
        // person appears exactly once regardless of name-formatting variations.

        $personnelWorkload = DB::table('users')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.name', 'MIS')
            ->leftJoin('it_job_requests', DB::raw('LOWER(TRIM(it_job_requests.attendedby))'), '=', DB::raw('LOWER(TRIM(users.name))'))
            ->select([
                'users.id',
                DB::raw("users.name as attendedby"),
                DB::raw("COUNT(it_job_requests.id) as total"),
                DB::raw("ROUND(AVG(CASE WHEN it_job_requests.rating IS NOT NULL THEN it_job_requests.rating END), 1) as avg_rating"),
                DB::raw("SUM(CASE WHEN it_job_requests.status = 'Acted by MIS' THEN 1 ELSE 0 END) as pending"),
            ])
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->get();

        // ── CSM SQD Breakdown ─────────────────────────────────────────────────

        $sqdRaw = CsmResponse::where('respondable_type', ITJobRequest::class)
            ->selectRaw("
                ROUND(AVG(NULLIF(sqd0,6)),1) as sqd0,
                ROUND(AVG(NULLIF(sqd1,6)),1) as sqd1,
                ROUND(AVG(NULLIF(sqd2,6)),1) as sqd2,
                ROUND(AVG(NULLIF(sqd3,6)),1) as sqd3,
                ROUND(AVG(NULLIF(sqd4,6)),1) as sqd4,
                ROUND(AVG(NULLIF(sqd5,6)),1) as sqd5,
                ROUND(AVG(NULLIF(sqd6,6)),1) as sqd6,
                ROUND(AVG(NULLIF(sqd7,6)),1) as sqd7,
                ROUND(AVG(NULLIF(sqd8,6)),1) as sqd8,
                COUNT(*) as total_responses
            ")
            ->first();

        $sqdLabels = [
            'SQD0' => 'Overall Satisfaction',
            'SQD1' => 'Reasonable Time',
            'SQD2' => 'Followed Requirements',
            'SQD3' => 'Steps Were Simple',
            'SQD4' => 'Info from Website',
            'SQD5' => 'Reasonable Fees',
            'SQD6' => 'Transaction Secure',
            'SQD7' => 'Support Responsive',
            'SQD8' => 'Got What Was Needed',
        ];

        $sqdBreakdown = collect($sqdLabels)->map(fn ($label, $key) => [
            'label' => $label,
            'score' => (float) ($sqdRaw->{strtolower($key)} ?? 0),
        ])->values();

        // ── Recent Requests (last 10) ─────────────────────────────────────────

        // Include `assignedto` so the $appends accessor (assigned_personnel) can
        // resolve the relationship without throwing "Undefined property".
        $recentRequests = ITJobRequest::with(['user:id,name', 'assignedTo:id,name'])
            ->orderByDesc('created_at')
            ->take(10)
            ->get(['id', 'itjr_no', 'title', 'category', 'user_id', 'assignedto', 'status', 'created_at'])
            ->makeHidden(['assigned_personnel']);

        return Inertia::render('MIS/Dashboard', [
            'month'              => $month,
            'kpi'                => [
                'total_this_month'     => $totalThisMonth,
                'completed_this_month' => $completedThisMonth,
                'pending_action'       => $pendingAction,
                'avg_rating'           => $avgRating ? round((float) $avgRating, 1) : null,
                'avg_response_days'    => $avgResponseDays,
                'total_csm_responses'  => (int) ($sqdRaw->total_responses ?? 0),
            ],
            'statusBreakdown'    => $statusBreakdown,
            'categoryBreakdown'  => $categoryBreakdown,
            'monthlyTrend'       => $monthlyTrend,
            'personnelWorkload'  => $personnelWorkload,
            'sqdBreakdown'       => $sqdBreakdown,
            'recentRequests'     => $recentRequests,
        ]);
    }

    public function architecture()
    {
        return \Inertia\Inertia::render('MIS/Architecture');
    }
}
