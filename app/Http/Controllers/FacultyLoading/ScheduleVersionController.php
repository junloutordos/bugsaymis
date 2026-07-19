<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\ScheduleVersion;
use App\Services\FacultyLoading\ScheduleVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Save/compare/restore labeled schedule "versions" — an on-demand checkpoint
 * of the term's current tentative schedule, independent of the AI-job undo
 * and the formal CID→OCD submission snapshot (see ScheduleVersionService).
 *
 * Save/Restore/Delete are CID/admin-only; Compare is also open to OCD so they
 * can review checkpoints without being able to mutate the live calendar.
 */
class ScheduleVersionController extends Controller
{
    public function __construct(private readonly ScheduleVersionService $service) {}

    private function isManage(): bool
    {
        $user = Auth::user();

        return $user->isSuperAdmin() || $user->hasPermission('faculty_loading.manage');
    }

    private function canReview(): bool
    {
        $user = Auth::user();

        return $this->isManage() || $user->hasPermission('faculty_loading.approve') || $user->hasRole('OCD');
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->canReview(), 403);

        $data = $request->validate(['academic_term_id' => 'required|integer']);

        $versions = ScheduleVersion::forTerm($data['academic_term_id'])
            ->with('createdBy:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ScheduleVersion $v) => [
                'id'              => $v->id,
                'label'           => $v->label,
                'notes'           => $v->notes,
                'session_count'   => $v->session_count,
                'created_by_name' => $v->createdBy?->name ?? '—',
                'created_at'      => $v->created_at?->toISOString(),
                'restored_at'     => $v->restored_at?->toISOString(),
            ]);

        return response()->json($versions);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->isManage(), 403);

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

        return back()->with('success', 'Schedule version saved.');
    }

    public function compare(Request $request): JsonResponse
    {
        abort_unless($this->canReview(), 403);

        $data = $request->validate([
            'academic_term_id' => 'required|integer',
            'version_a_id'     => 'required|integer|exists:schedule_versions,id',
            'version_b_id'     => 'nullable|integer|exists:schedule_versions,id',
        ]);

        $a = ScheduleVersion::findOrFail($data['version_a_id']);
        $b = isset($data['version_b_id']) ? ScheduleVersion::findOrFail($data['version_b_id']) : null;

        return response()->json($this->service->compare($a, $b, $data['academic_term_id']));
    }

    public function restore(ScheduleVersion $scheduleVersion): RedirectResponse
    {
        abort_unless($this->isManage(), 403);

        try {
            $this->service->restore($scheduleVersion);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['schedule' => $e->getMessage()]);
        }

        return back()->with('success', "Restored \"{$scheduleVersion->label}\".");
    }

    public function destroy(ScheduleVersion $scheduleVersion): RedirectResponse
    {
        abort_unless($this->isManage(), 403);

        $scheduleVersion->delete();

        return back()->with('success', 'Schedule version deleted.');
    }
}
