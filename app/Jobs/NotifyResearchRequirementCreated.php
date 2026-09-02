<?php

namespace App\Jobs;

use App\Mail\ResearchRequirementMail;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Models\FacultyLoading\ResearchRequirementAssignment;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class NotifyResearchRequirementCreated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public int $requirementId, public array $assignmentIds)
    {
        $this->onQueue('bulk');
    }

    public function handle(): void
    {
        $requirement = ResearchRequirement::find($this->requirementId);
        if (! $requirement) {
            logger()->error('NotifyResearchRequirementCreated: requirement not found', ['requirement_id' => $this->requirementId]);
            return;
        }

        $assignments = ResearchRequirementAssignment::with('researchGroup')->whereIn('id', $this->assignmentIds)->get();

        $sent = 0;
        foreach ($assignments as $assignment) {
            $advisers = ResearchAdvisory::where('research_group_id', $assignment->research_group_id)
                ->where('status', '<>', 'dropped')
                ->with('faculty')
                ->get()
                ->pluck('faculty')
                ->filter();

            foreach ($advisers as $user) {
                try {
                    NotificationService::notifyUser(
                        $user,
                        'Research Requirement',
                        $requirement->title,
                        'A new submission requirement has been posted for your research group.',
                        route('faculty-loading.my-research-requirements.index'),
                    );

                    if ($user->email) {
                        Mail::to($user->email)->send(new ResearchRequirementMail(
                            recipientName: $user->name,
                            headerTitle: 'New Research Requirement Posted',
                            lead: "A new submission requirement has been posted for \"{$assignment->researchGroup->title}\".",
                            details: [
                                ['Requirement', $requirement->title],
                                ['Due', $requirement->due_at->format('F j, Y g:i A')],
                            ],
                            actionUrl: route('faculty-loading.my-research-requirements.index'),
                            actionLabel: 'View Requirement',
                        ));
                    }
                    $sent++;
                } catch (\Throwable $e) {
                    logger()->warning('NotifyResearchRequirementCreated: notify failed', [
                        'requirement_id' => $requirement->id, 'user_id' => $user->id, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        logger()->info('NotifyResearchRequirementCreated: complete', ['requirement_id' => $requirement->id, 'sent' => $sent]);
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('NotifyResearchRequirementCreated: job FAILED', ['requirement_id' => $this->requirementId, 'error' => $e->getMessage()]);
    }
}
