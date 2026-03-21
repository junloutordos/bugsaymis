<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HrmpsbController extends Controller
{
    private function getRole(): Role
    {
        return Role::where('name', 'HRMPSB')->firstOrFail();
    }

    /**
     * Show current HRMPSB members + user search.
     */
    public function index(Request $request)
    {
        $this->authorize('recruitment.manage');

        $role    = $this->getRole();
        $members = User::whereHas('roles', fn ($q) => $q->where('roles.name', 'HRMPSB'))
            ->select('id', 'name', 'email', 'badge_id', 'position')
            ->orderBy('name')
            ->get();

        // Search users to add
        $search  = $request->string('search')->trim();
        $results = collect();

        if ($search->isNotEmpty()) {
            $memberIds = $members->pluck('id');
            $results = User::where(fn ($q) =>
                    $q->where('name',     'like', "%{$search}%")
                      ->orWhere('email',  'like', "%{$search}%")
                      ->orWhere('badge_id', 'like', "%{$search}%")
                )
                ->whereNotIn('id', $memberIds)
                ->select('id', 'name', 'email', 'badge_id', 'position')
                ->limit(15)
                ->get();
        }

        return Inertia::render('Recruitment/HRMPSB/Index', [
            'members' => $members,
            'results' => $results,
            'search'  => $search,
        ]);
    }

    /**
     * Assign the HRMPSB role to a user.
     */
    public function assign(Request $request)
    {
        $this->authorize('recruitment.manage');

        $request->validate(['user_id' => 'required|integer|exists:users,id']);

        $role = $this->getRole();
        $user = User::findOrFail($request->user_id);

        // Add role via pivot (syncWithoutDetaching preserves other roles)
        $user->roles()->syncWithoutDetaching([$role->id]);
        $user->clearPermissionCache();

        return back()->with('success', "{$user->name} has been added as an HRMPSB member.");
    }

    /**
     * Remove the HRMPSB role from a user.
     */
    public function remove(Request $request, User $user)
    {
        $this->authorize('recruitment.manage');

        $role = $this->getRole();
        $user->roles()->detach($role->id);
        $user->clearPermissionCache();

        return back()->with('success', "{$user->name} has been removed from the HRMPSB.");
    }
}
