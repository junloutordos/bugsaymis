<?php

namespace App\Http\Controllers;

use App\Services\GeneralServicesDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GeneralServicesDashboardController extends Controller
{
    public function index(Request $request, GeneralServicesDashboardService $dashboard)
    {
        $this->authorizeAccess($request);

        return Inertia::render('GeneralServices/Dashboard', $dashboard->payload());
    }

    public function events(Request $request, GeneralServicesDashboardService $dashboard)
    {
        $this->authorizeAccess($request);

        $data = $request->validate([
            'start' => 'nullable|date',
            'end'   => 'nullable|date',
        ]);

        return response()->json($dashboard->calendarEvents(
            $data['start'] ?? null,
            $data['end'] ?? null,
        ));
    }

    private function authorizeAccess(Request $request): void
    {
        $user = $request->user();
        $isFadChief = $user && (str_contains($user->position ?? '', 'FAD') || $user->hasRole('FAD Chief'));

        if (! $user || ! (
            $user->hasAnyRole(['Administrator', 'GSU Head', 'OCD', 'DivisionChief'])
            || $isFadChief
        )) {
            abort(403);
        }
    }
}
