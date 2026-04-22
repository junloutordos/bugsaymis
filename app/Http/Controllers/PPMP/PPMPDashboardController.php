<?php

namespace App\Http\Controllers\PPMP;

use App\Http\Controllers\Controller;
use App\Models\PPMP\Ppmp;
use App\Services\PPMP\PPMPDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PPMPDashboardController extends Controller
{
    public function index(Request $request, PPMPDashboardService $dashboard)
    {
        $fiscalYear = $request->input('fiscal_year', (int) date('Y'));

        return Inertia::render('PPMP/Dashboard', [
            'fiscalYear'  => $fiscalYear,
            'fiscalYears' => Ppmp::distinct()->pluck('fiscal_year')->push((int) date('Y'))->unique()->sort()->values(),
            'metrics'     => $dashboard->getMetrics((int) $fiscalYear),
        ]);
    }
}
