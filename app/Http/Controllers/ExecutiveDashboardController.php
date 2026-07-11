<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Services\ExecutiveDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExecutiveDashboardController extends Controller
{
    public function __construct(private ExecutiveDashboardService $dashboard)
    {
    }

    public function index(Request $request)
    {
        [$divisionId, $isCampusLens, $division] = $this->resolveLens($request);

        return Inertia::render('Executive/Dashboard', array_merge(
            $this->dashboard->build($divisionId),
            [
                'lens' => [
                    'campus'       => $isCampusLens,
                    'division'     => $division?->only('id', 'division_name'),
                    'divisionId'   => $divisionId,
                ],
                'divisions' => $isCampusLens
                    ? Division::where('status', 'active')->orderBy('division_name')->get(['id', 'division_name'])
                    : [],
            ]
        ));
    }

    public function refresh(Request $request)
    {
        [$divisionId] = $this->resolveLens($request);

        $this->dashboard->forget($divisionId);

        return back()->with('success', 'Dashboard data refreshed.');
    }

    /**
     * OCD / Administrator: campus-wide, optionally filtered to one division.
     * A Division Chief is locked to their own division.
     *
     * @return array{0: ?int, 1: bool, 2: ?Division}
     */
    private function resolveLens(Request $request): array
    {
        $user = $request->user();

        $ownDivisionId = Division::where('division_chief_id', $user->id)->value('id');
        $isCampusLens  = $user->isSuperAdmin() || $user->hasRole('OCD') || ! $ownDivisionId;

        $divisionId = $isCampusLens
            ? ($request->integer('division_id') ?: null)
            : $ownDivisionId;

        $division = $divisionId ? Division::find($divisionId) : null;

        return [$divisionId, $isCampusLens, $division];
    }
}
