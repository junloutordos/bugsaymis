<?php

namespace App\Http\Controllers;

use App\Models\SpecialAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SpecialAssignmentController extends Controller
{
    public function index()
    {
        return Inertia::render('DataManagement/SpecialAssignments/Index', [
            'assignments' => SpecialAssignment::with(['coordinator', 'members'])->orderBy('name')->get(),
            'users'       => User::select('id', 'name', 'position')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'coordinator_id' => 'nullable|exists:users,id',
            'description'    => 'nullable|string',
            'member_ids'     => 'nullable|array',
            'member_ids.*'   => 'exists:users,id',
            'member_tasks'   => 'nullable|array',
        ]);

        $assignment = SpecialAssignment::create([
            'name'           => $validated['name'],
            'coordinator_id' => $validated['coordinator_id'] ?? null,
            'description'    => $validated['description'] ?? null,
        ]);

        $this->syncMembers($assignment, $validated['member_ids'] ?? [], $request->input('member_tasks', []));

        return redirect()->back()->with('success', 'Special Assignment created.');
    }

    public function update(Request $request, SpecialAssignment $specialAssignment)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'coordinator_id' => 'nullable|exists:users,id',
            'description'    => 'nullable|string',
            'member_ids'     => 'nullable|array',
            'member_ids.*'   => 'exists:users,id',
            'member_tasks'   => 'nullable|array',
        ]);

        $specialAssignment->update([
            'name'           => $validated['name'],
            'coordinator_id' => $validated['coordinator_id'] ?? null,
            'description'    => $validated['description'] ?? null,
        ]);

        $this->syncMembers($specialAssignment, $validated['member_ids'] ?? [], $request->input('member_tasks', []));

        return redirect()->back()->with('success', 'Special Assignment updated.');
    }

    public function destroy(SpecialAssignment $specialAssignment)
    {
        $specialAssignment->delete();
        return redirect()->back()->with('success', 'Special Assignment deleted.');
    }

    private function syncMembers(SpecialAssignment $assignment, array $memberIds, array $memberTasks): void
    {
        $syncData = [];
        foreach ($memberIds as $userId) {
            $syncData[$userId] = ['task' => $memberTasks[$userId] ?? null];
        }
        $assignment->members()->sync($syncData);
    }
}
