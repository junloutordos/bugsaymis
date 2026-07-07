<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Services\StudentPortal\RhPortalService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RhLeavePassController extends Controller
{
    public function index(RhPortalService $service)
    {
        return Inertia::render('StudentPortal/RhLeavePasses', $service->leavePassData(session('student_pisaysystemID')));
    }

    public function store(Request $request, RhPortalService $service)
    {
        $error = $service->storeLeavePass($request, session('student_pisaysystemID'));

        if ($error) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Leave pass filed. Await approval from your Dorm Manager.');
    }
}
