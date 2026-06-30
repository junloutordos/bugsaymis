<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\User;
use App\Services\FacultyLoading\LoadComputationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CommitteeController extends Controller
{
    public function __construct(private readonly LoadComputationService $loads) {}

    public function index()
    {
        $committees = Committee::with(['head', 'members', 'subCommittees.head', 'subCommittees.members'])
            ->whereNull('parent_committee_id')
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id'          => $c->id,
                'name'        => $c->name,
                'head_id'     => $c->head_id,
                'head'        => $c->head?->only('id', 'name'),
                'description' => $c->description,
                'members'     => $c->members->map(fn ($m) => [
                    'id'    => $m->id,
                    'name'  => $m->name,
                    'pivot' => ['task' => $m->pivot->task],
                ]),
                'sub_committees' => $c->subCommittees->map(fn ($sub) => [
                    'id'      => $sub->id,
                    'name'    => $sub->name,
                    'head_id' => $sub->head_id,
                    'head'    => $sub->head?->only('id', 'name'),
                    'members' => $sub->members->map(fn ($m) => [
                        'id'    => $m->id,
                        'name'  => $m->name,
                        'pivot' => ['task' => $m->pivot->task],
                    ]),
                ])->values(),
            ]);

        $terms = AcademicTerm::with('schoolYear')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($t) => [
                'id'             => $t->id,
                'school_year_id' => $t->school_year_id,
                'label'          => $t->full_label,
                'is_current'     => $t->is_current,
            ]);

        return Inertia::render('DataManagement/Committees/Index', [
            'committees' => $committees,
            'users'      => User::select('id', 'name', 'position')->orderBy('name')->get(),
            'terms'      => $terms,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'                             => 'required|string|max:255',
            'head_id'                          => 'nullable|exists:users,id',
            'description'                      => 'nullable|string',
            'has_subcommittees'                => 'boolean',
            'member_ids'                       => 'nullable|array',
            'member_ids.*'                     => 'exists:users,id',
            'member_tasks'                     => 'nullable|array',
            'sub_committees'                   => 'nullable|array',
            'sub_committees.*.name'            => 'required_with:sub_committees|string|max:255',
            'sub_committees.*.head_id'         => 'nullable|exists:users,id',
            'sub_committees.*.member_ids'      => 'nullable|array',
            'sub_committees.*.member_ids.*'    => 'exists:users,id',
            'sub_committees.*.member_tasks'    => 'nullable|array',
        ]);

        $committee = Committee::create([
            'name'        => $validated['name'],
            'head_id'     => $validated['head_id'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        if (!empty($validated['has_subcommittees'])) {
            foreach ($validated['sub_committees'] ?? [] as $subData) {
                $sub = Committee::create([
                    'name'               => $subData['name'],
                    'head_id'            => $subData['head_id'] ?? null,
                    'parent_committee_id' => $committee->id,
                ]);
                $this->syncMembers($sub, $subData['member_ids'] ?? [], $subData['member_tasks'] ?? []);
            }
        } else {
            $this->syncMembers($committee, $validated['member_ids'] ?? [], $request->input('member_tasks', []));
        }

        return back()->with('success', 'Committee created.');
    }

    public function update(Request $request, Committee $committee): RedirectResponse
    {
        $validated = $request->validate([
            'name'                             => 'required|string|max:255',
            'head_id'                          => 'nullable|exists:users,id',
            'description'                      => 'nullable|string',
            'has_subcommittees'                => 'boolean',
            'member_ids'                       => 'nullable|array',
            'member_ids.*'                     => 'exists:users,id',
            'member_tasks'                     => 'nullable|array',
            'sub_committees'                   => 'nullable|array',
            'sub_committees.*.id'              => 'nullable|exists:committees,id',
            'sub_committees.*.name'            => 'required_with:sub_committees|string|max:255',
            'sub_committees.*.head_id'         => 'nullable|exists:users,id',
            'sub_committees.*.member_ids'      => 'nullable|array',
            'sub_committees.*.member_ids.*'    => 'exists:users,id',
            'sub_committees.*.member_tasks'    => 'nullable|array',
        ]);

        $committee->update([
            'name'        => $validated['name'],
            'head_id'     => $validated['head_id'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        if (!empty($validated['has_subcommittees'])) {
            foreach ($validated['sub_committees'] ?? [] as $subData) {
                if (!empty($subData['id'])) {
                    $sub = Committee::find($subData['id']);
                    if ($sub && $sub->parent_committee_id === $committee->id) {
                        $sub->update(['name' => $subData['name'], 'head_id' => $subData['head_id'] ?? null]);
                        $this->syncMembers($sub, $subData['member_ids'] ?? [], $subData['member_tasks'] ?? []);
                    }
                } else {
                    $sub = Committee::create([
                        'name'               => $subData['name'],
                        'head_id'            => $subData['head_id'] ?? null,
                        'parent_committee_id' => $committee->id,
                    ]);
                    $this->syncMembers($sub, $subData['member_ids'] ?? [], $subData['member_tasks'] ?? []);
                }
            }
        } else {
            $this->syncMembers($committee, $validated['member_ids'] ?? [], $request->input('member_tasks', []));
        }

        return back()->with('success', 'Committee updated.');
    }

    public function destroy(Committee $committee): RedirectResponse
    {
        $committee->delete();
        return back()->with('success', 'Committee deleted.');
    }

    /** Sync committee members (and sub-committee members) into faculty_committee_assignments for a term. */
    public function syncToTerm(Request $request, Committee $committee): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'academic_term_id' => 'required|exists:academic_terms,id',
            'school_year_id'   => 'required|exists:school_years,id',
        ]);

        $committee->load(['members', 'subCommittees.members']);

        $synced = 0;

        $this->syncCommitteeToTerm($committee, (int) $data['school_year_id'], (int) $data['academic_term_id'], $synced);

        foreach ($committee->subCommittees as $sub) {
            $this->syncCommitteeToTerm($sub, (int) $data['school_year_id'], (int) $data['academic_term_id'], $synced);
        }

        return back()->with('success', "{$synced} assignment(s) synced to the selected term.");
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function syncMembers(Committee $committee, array $memberIds, array $memberTasks): void
    {
        $syncData = [];
        foreach ($memberIds as $userId) {
            $syncData[$userId] = ['task' => $memberTasks[$userId] ?? null];
        }
        $committee->members()->sync($syncData);
    }

    private function syncCommitteeToTerm(Committee $committee, int $syId, int $termId, int &$synced): void
    {
        if ($committee->head_id) {
            $this->createAssignmentIfNew($committee, $committee->head_id, 'chairperson', $syId, $termId, $synced);
        }

        foreach ($committee->members as $member) {
            if ($member->id === $committee->head_id) continue;
            $this->createAssignmentIfNew($committee, $member->id, 'member', $syId, $termId, $synced);
        }
    }

    private function createAssignmentIfNew(Committee $committee, int $userId, string $role, int $syId, int $termId, int &$synced): void
    {
        $exists = FacultyCommitteeAssignment::where('user_id', $userId)
            ->where('academic_term_id', $termId)
            ->where('committee_id', $committee->id)
            ->where('status', 'active')
            ->exists();

        if ($exists) return;

        $loadUnits = $committee->loadUnitsFor($role);

        $load = $this->loads->findOrCreateFacultyLoad($userId, $syId, $termId);

        $la = LoadAssignment::create([
            'faculty_load_id'  => $load->id,
            'user_id'          => $userId,
            'school_year_id'   => $syId,
            'academic_term_id' => $termId,
            'assignment_type'  => 'committee',
            'load_units'       => $loadUnits,
            'description'      => "{$committee->name} ({$role})",
            'created_by'       => Auth::id(),
        ]);

        FacultyCommitteeAssignment::create([
            'user_id'            => $userId,
            'school_year_id'     => $syId,
            'academic_term_id'   => $termId,
            'committee_id'       => $committee->id,
            'committee_name'     => $committee->name,
            'role'               => $role,
            'load_units'         => $loadUnits,
            'status'             => 'active',
            'load_assignment_id' => $la->id,
        ]);

        $this->loads->syncLoad($load);
        $synced++;
    }
}
