<?php

namespace App\Http\Controllers;

use App\Services\PersonalDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request, PersonalDashboardService $dashboard)
    {
        return Inertia::render('Dashboard', $dashboard->payload($request->user()));
    }
}
