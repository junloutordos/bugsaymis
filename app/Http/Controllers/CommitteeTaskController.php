<?php

namespace App\Http\Controllers;

use App\Mail\CommitteeTaskAssignedMail;
use App\Mail\CommitteeTaskStatusMail;
use App\Models\Committee;
use App\Models\CommitteeTask;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CommitteeBoardService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * monday.com-style task board shared by the Performance Management and
 * Faculty Loading committee pages. Managers (heads / sub-heads / admins)
 * run the board; members may self-add tasks and work their own items.
 */
class CommitteeTaskController extends Controller
{
    public function __construct(private CommitteeBoardService $board)
    {
    }

    public function store(Request $request, Committee $committee): RedirectResponse
    {
        $user      = auth()->user();
        $isManager = $this->board->canManageBoard($user, $committee);

        abort_unless(
            $isManager || $this->board->isBoardMember($user, $committee),
            403,
            'Only committee members can add tasks to this board.'
        );

        $data = $this->validateTask($request);

        $assigneeIds = collect($data['assignee_ids'] ?? []);
        if (! $isManager) {
            // Members may only add tasks for themselves
            $assigneeIds = collect([$user->id]);
        }

        $task = CommitteeTask::create([
            'committee_id'              => $committee->id,
            'rating_period_id'          => $data['rating_period_id'] ?? null,
            'work_distribution_plan_id' => $data['work_distribution_plan_id'] ?? null,
            'title'                     => $data['title'],
            'description'               => $data['description'] ?? null,
            'status'                    => $data['status'] ?? 'not_started',
            'priority'                  => $data['priority'] ?? 'medium',
            'due_date'                  => $data['due_date'] ?? null,
            'sort_order'                => (int) (CommitteeTask::where('committee_id', $committee->id)->max('sort_order') + 1),
            'created_by'                => $user->id,
            'completed_at'              => ($data['status'] ?? null) === 'done' ? now() : null,
        ]);

        $task->assignees()->sync($assigneeIds);

        $this->notifyAssignees($task, $assigneeIds->reject(fn ($id) => $id == $user->id));

        AuditLogger::log([
            'action'         => 'committee_task_created',
            'auditable_type' => CommitteeTask::class,
            'auditable_id'   => $task->id,
            'new_values'     => ['title' => $task->title, 'committee_id' => $committee->id],
        ]);

        return back()->with('success', 'Task added to the board.');
    }

    public function update(Request $request, CommitteeTask $task): RedirectResponse
    {
        $task->loadMissing('committee');
        $this->board->assertCanManageBoard(auth()->user(), $task->committee);

        $data = $this->validateTask($request);

        $task->update([
            'rating_period_id'          => $data['rating_period_id'] ?? null,
            'work_distribution_plan_id' => $data['work_distribution_plan_id'] ?? null,
            'title'                     => $data['title'],
            'description'               => $data['description'] ?? null,
            'priority'                  => $data['priority'] ?? $task->priority,
            'due_date'                  => $data['due_date'] ?? null,
        ]);

        if (array_key_exists('assignee_ids', $data)) {
            $before = $task->assignees()->pluck('users.id');
            $task->assignees()->sync($data['assignee_ids'] ?? []);
            $added = collect($data['assignee_ids'] ?? [])->diff($before);
            $this->notifyAssignees($task, $added->reject(fn ($id) => $id == auth()->id()));
        }

        return back()->with('success', 'Task updated.');
    }

    public function updateStatus(Request $request, CommitteeTask $task): RedirectResponse
    {
        $task->loadMissing('committee', 'assignees');
        $user = auth()->user();

        $isManager  = $this->board->canManageBoard($user, $task->committee);
        $isAssignee = $task->assignees->contains('id', $user->id);
        abort_unless($isManager || $isAssignee, 403, 'Only assignees or the committee head can move this task.');

        $data = $request->validate([
            'status'     => 'required|in:' . implode(',', CommitteeTask::STATUSES),
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $from = $task->status;
        $task->update([
            'status'       => $data['status'],
            'sort_order'   => $data['sort_order'] ?? $task->sort_order,
            'completed_at' => $data['status'] === 'done' ? ($task->completed_at ?? now()) : null,
        ]);

        // monday practice: heads hear about Done and Stuck, not every nudge
        if ($from !== $data['status'] && in_array($data['status'], ['done', 'stuck'], true)) {
            foreach ($this->board->boardManagers($task->committee)->reject(fn ($m) => $m->id === $user->id) as $manager) {
                NotificationService::notifyUser(
                    $manager,
                    'Committee Task',
                    $task->title,
                    $data['status'] === 'done' ? 'Done' : 'Stuck',
                    $this->boardUrl($task),
                    "{$task->committee->name} — updated by {$user->name}"
                );
                Mail::to($manager->email)->send(new CommitteeTaskStatusMail($task, $manager->name, $user));
            }
        }

        return back()->with('success', 'Task status updated.');
    }

    public function storeUpdate(Request $request, CommitteeTask $task): RedirectResponse
    {
        $task->loadMissing('committee', 'assignees');
        $user = auth()->user();

        abort_unless(
            $this->board->canManageBoard($user, $task->committee) || $task->assignees->contains('id', $user->id),
            403,
            'Only assignees or the committee head can post updates on this task.'
        );

        $data = $request->validate(['body' => 'required|string|max:2000']);

        $task->updates()->create(['user_id' => $user->id, 'body' => $data['body']]);

        return back()->with('success', 'Update posted.');
    }

    public function destroy(CommitteeTask $task): RedirectResponse
    {
        $task->loadMissing('committee');
        $this->board->assertCanManageBoard(auth()->user(), $task->committee);

        AuditLogger::log([
            'action'         => 'committee_task_deleted',
            'auditable_type' => CommitteeTask::class,
            'auditable_id'   => $task->id,
            'new_values'     => ['title' => $task->title],
        ]);

        $task->delete();

        return back()->with('success', 'Task removed from the board.');
    }

    public function reorder(Request $request, Committee $committee): RedirectResponse
    {
        $this->board->assertCanManageBoard(auth()->user(), $committee);

        $data = $request->validate([
            'task_ids'   => 'required|array',
            'task_ids.*' => 'integer|exists:committee_tasks,id',
        ]);

        foreach ($data['task_ids'] as $order => $taskId) {
            CommitteeTask::where('id', $taskId)
                ->where('committee_id', $committee->id)
                ->update(['sort_order' => $order]);
        }

        return back();
    }

    private function validateTask(Request $request): array
    {
        return $request->validate([
            'title'                     => 'required|string|max:255',
            'description'               => 'nullable|string|max:5000',
            'status'                    => 'nullable|in:' . implode(',', CommitteeTask::STATUSES),
            'priority'                  => 'nullable|in:' . implode(',', CommitteeTask::PRIORITIES),
            'due_date'                  => 'nullable|date',
            'rating_period_id'          => 'nullable|exists:ipcr_rating_periods,id',
            'work_distribution_plan_id' => 'nullable|exists:work_distribution_plans,id',
            'assignee_ids'              => 'nullable|array',
            'assignee_ids.*'            => 'integer|exists:users,id',
        ]);
    }

    private function notifyAssignees(CommitteeTask $task, $userIds): void
    {
        if ($userIds->isEmpty()) {
            return;
        }

        $task->loadMissing('committee');

        foreach (User::whereIn('id', $userIds)->where('status', '<>', 'inactive')->get() as $assignee) {
            NotificationService::notifyUser(
                $assignee,
                'Committee Task',
                $task->title,
                'Assigned to you',
                $this->boardUrl($task),
                $task->committee->name
            );
            Mail::to($assignee->email)->send(new CommitteeTaskAssignedMail($task, $assignee->name));
        }
    }

    private function boardUrl(CommitteeTask $task): string
    {
        // Both surfaces show the same board; PM page is the canonical link
        $committeeId = $task->committee->parent_committee_id ?? $task->committee_id;

        return route('pm-committees.show', $committeeId, false);
    }
}
