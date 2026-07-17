<?php

namespace App\Http\Controllers;

use App\Services\FacultyDashboardService;
use App\Services\PersonalDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request, PersonalDashboardService $dashboard, FacultyDashboardService $faculty)
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            ...$dashboard->payload($user),
            'faculty' => $faculty->payload($user),
        ]);
    }
}
