<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\LoadAssignmentVersion;
use App\Services\FacultyLoading\LoadAssignmentVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Save/compare/restore labeled "versions" of a term's load_assignments.
 * Single-tier — this module has no OCD/review role the way schedules do
 * (only per-faculty FacultyLoad.is_locked), so every action here is gated
 * to faculty_loading.manage, matching LoadAssignmentController's own gating.
 */
class LoadAssignmentVersionController extends Controller
{
    public function __construct(private readonly LoadAssignmentVersionService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate(['academic_term_id' => 'required|integer']);

        $versions = LoadAssignmentVersion::forTerm($data['academic_term_id'])
            ->with('createdBy:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (LoadAssignmentVersion $v) => [
                'id'                => $v->id,
                'label'             => $v->label,
                'notes'             => $v->notes,
                'assignment_count'  => $v->assignment_count,
                'created_by_name'   => $v->createdBy?->name ?? '—',
                'created_at'        => $v->created_at?->toISOString(),
                'restored_at'       => $v->restored_at?->toISOString(),
            ]);

        return response()->json($versions);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'academic_term_id' => 'required|integer',
            'label'            => 'required|string|max:100',
            'notes'            => 'nullable|string|max:2000',
        ]);

        $this->service->save(
            $data['academic_term_id'],
            $data['label'],
            $data['notes'] ?? null,
            Auth::id(),
        );

        return back()->with('success', 'Load assignment version saved.');
    }

    public function compare(Request $request): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'academic_term_id' => 'required|integer',
            'version_a_id'     => 'required|integer|exists:load_assignment_versions,id',
            'version_b_id'     => 'nullable|integer|exists:load_assignment_versions,id',
        ]);

        $a = LoadAssignmentVersion::findOrFail($data['version_a_id']);
        $b = isset($data['version_b_id']) ? LoadAssignmentVersion::findOrFail($data['version_b_id']) : null;

        return response()->json($this->service->compare($a, $b, $data['academic_term_id']));
    }

    public function restore(LoadAssignmentVersion $loadAssignmentVersion): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $result = $this->service->restore($loadAssignmentVersion);

        $message = "Restored \"{$loadAssignmentVersion->label}\" ({$result['restored']} assignment(s) updated).";
        if (! empty($result['skipped_locked'])) {
            $message .= ' Skipped (load locked): '.implode(', ', $result['skipped_locked']).'.';
        }

        return back()->with('success', $message);
    }

    public function destroy(LoadAssignmentVersion $loadAssignmentVersion): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $loadAssignmentVersion->delete();

        return back()->with('success', 'Load assignment version deleted.');
    }
}
