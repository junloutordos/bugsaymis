<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\ApprovePlacementRequest;
use App\Models\Application;
use App\Models\OnboardingTask;
use App\Models\Placement;
use App\Services\Recruitment\OnboardingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlacementController extends Controller
{
    public function __construct(private OnboardingService $service) {}

    /**
     * List all placements (HR onboarding tracker).
     */
    public function index(Request $request)
    {
        $this->authorize('recruitment.view');

        $query = Placement::with([
            'application.applicant',
            'application.jobVacancy.jobItem.recruitmentType',
            'office',
        ])->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $placements = $query->paginate(20)->withQueryString();

        return Inertia::render('Recruitment/Placements/Index', [
            'placements' => $placements,
            'filters'    => $request->only(['status']),
        ]);
    }

    /**
     * Onboarding detail view for a placement.
     */
    public function show(Placement $placement)
    {
        $this->authorize('recruitment.view');

        $placement->load([
            'application.applicant',
            'application.jobVacancy.jobItem.recruitmentType',
            'application.selection',
            'office',
            'onboardingTasks.assignee',
        ]);

        return Inertia::render('Recruitment/Placements/Show', [
            'placement' => $placement,
        ]);
    }

    /**
     * Approve selection and create placement + onboarding tasks.
     */
    public function approve(ApprovePlacementRequest $request, Application $application)
    {
        if ($application->current_stage !== 'selection') {
            return back()->withErrors(['error' => 'Application must be in the selection stage to approve.']);
        }

        try {
            $placement = $this->service->approveAndPlace(
                $application,
                $request->validated(),
                $request->user()->id
            );
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', "Placement created. {$placement->onboardingTasks->count()} onboarding tasks generated.");
    }

    /**
     * Disapprove selection.
     */
    public function disapprove(Request $request, Application $application)
    {
        $this->authorize('recruitment.approve');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->service->disapprove($application, $request->user()->id, $validated['reason']);

        return back()->with('success', 'Selection disapproved.');
    }

    /**
     * Mark an onboarding task as complete.
     */
    public function completeTask(Request $request, Placement $placement, OnboardingTask $task)
    {
        $this->authorize('recruitment.onboarding');

        if ($task->placement_id !== $placement->id) {
            abort(403);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->service->completeTask($task, $validated['notes'] ?? '');

        return back()->with('success', "Task '{$task->task_name}' marked complete.");
    }

    /**
     * Skip an onboarding task.
     */
    public function skipTask(Request $request, Placement $placement, OnboardingTask $task)
    {
        $this->authorize('recruitment.onboarding');

        if ($task->placement_id !== $placement->id) {
            abort(403);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->service->skipTask($task, $validated['reason'] ?? '');

        return back()->with('success', "Task '{$task->task_name}' skipped.");
    }

    /**
     * Assign a user to an onboarding task.
     */
    public function assignTask(Request $request, Placement $placement, OnboardingTask $task)
    {
        $this->authorize('recruitment.onboarding');

        if ($task->placement_id !== $placement->id) {
            abort(403);
        }

        $validated = $request->validate([
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'due_date'    => ['nullable', 'date'],
        ]);

        $task->update($validated);

        return back()->with('success', 'Task assigned.');
    }
}
