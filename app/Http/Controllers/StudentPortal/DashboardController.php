<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Services\StudentPortal\PortalDashboardService;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(PortalDashboardService $service)
    {
        $overview = $service->overview(
            session('student_pisaysystemID'),
            (int) session('student_grade_level'),
        );

        return Inertia::render('StudentPortal/Dashboard', $overview);
    }
}
