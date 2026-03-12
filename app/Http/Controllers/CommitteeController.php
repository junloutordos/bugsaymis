<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CommitteeController extends Controller
{
    public function index()
    {
        return Inertia::render('DataManagement/Committees/Index', [
            'committees' => Committee::with(['head', 'members'])->orderBy('name')->get(),
            'users'      => User::select('id', 'name', 'position')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'head_id'     => 'nullable|exists:users,id',
            'description' => 'nullable|string',
            'member_ids'  => 'nullable|array',
            'member_ids.*' => 'exists:users,id',
            'member_tasks' => 'nullable|array',
        ]);

        $committee = Committee::create([
            'name'        => $validated['name'],
            'head_id'     => $validated['head_id'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        $this->syncMembers($committee, $validated['member_ids'] ?? [], $request->input('member_tasks', []));

        return redirect()->back()->with('success', 'Committee created.');
    }

    public function update(Request $request, Committee $committee)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'head_id'     => 'nullable|exists:users,id',
            'description' => 'nullable|string',
            'member_ids'  => 'nullable|array',
            'member_ids.*' => 'exists:users,id',
            'member_tasks' => 'nullable|array',
        ]);

        $committee->update([
            'name'        => $validated['name'],
            'head_id'     => $validated['head_id'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        $this->syncMembers($committee, $validated['member_ids'] ?? [], $request->input('member_tasks', []));

        return redirect()->back()->with('success', 'Committee updated.');
    }

    public function destroy(Committee $committee)
    {
        $committee->delete();
        return redirect()->back()->with('success', 'Committee deleted.');
    }

    private function syncMembers(Committee $committee, array $memberIds, array $memberTasks): void
    {
        $syncData = [];
        foreach ($memberIds as $userId) {
            $syncData[$userId] = ['task' => $memberTasks[$userId] ?? null];
        }
        $committee->members()->sync($syncData);
    }
}
