<?php

namespace App\Jobs;

use App\Mail\ResearchRequirementMail;
use App\Models\FacultyLoading\ResearchRequirementSubmission;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class NotifyResearchSubmissionReceived implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(public int $submissionId) {}

    public function handle(): void
    {
        $submission = ResearchRequirementSubmission::with(['assignment.requirement.createdBy', 'assignment.researchGroup', 'submittedBy'])->find($this->submissionId);
        if (! $submission) {
            logger()->error('NotifyResearchSubmissionReceived: submission not found', ['submission_id' => $this->submissionId]);
            return;
        }

        $coordinator = $submission->assignment->requirement->createdBy;
        if (! $coordinator) {
            return;
        }

        $url = route('faculty-loading.research-requirements.show', $submission->assignment->requirement->id);

        try {
            NotificationService::notifyUser(
                $coordinator,
                'Research Requirement',
                $submission->assignment->requirement->title,
                "{$submission->submittedBy?->name} submitted for \"{$submission->assignment->researchGroup->title}\".",
                $url,
            );

            if ($coordinator->email) {
                Mail::to($coordinator->email)->send(new ResearchRequirementMail(
                    recipientName: $coordinator->name,
                    headerTitle: 'New Research Submission Received',
                    lead: "{$submission->submittedBy?->name} submitted \"{$submission->assignment->requirement->title}\" for \"{$submission->assignment->researchGroup->title}\".",
                    details: [['Submitted', $submission->submitted_at->format('F j, Y g:i A')]],
                    actionUrl: $url,
                    actionLabel: 'Review Submission',
                ));
            }
        } catch (\Throwable $e) {
            logger()->warning('NotifyResearchSubmissionReceived: notify failed', ['submission_id' => $submission->id, 'error' => $e->getMessage()]);
        }
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('NotifyResearchSubmissionReceived: job FAILED', ['submission_id' => $this->submissionId, 'error' => $e->getMessage()]);
    }
}
