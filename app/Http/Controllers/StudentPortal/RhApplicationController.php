<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Services\StudentPortal\RhPortalService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RhApplicationController extends Controller
{
    public function show(RhPortalService $service)
    {
        return Inertia::render('StudentPortal/RhApplication', $service->applicationData(session('student_pisaysystemID')));
    }

    public function store(Request $request, RhPortalService $service)
    {
        $error = $service->storeApplication($request, session('student_pisaysystemID'));

        if ($error) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Application submitted. The Residence Hall Committee will review your application.');
    }
}
