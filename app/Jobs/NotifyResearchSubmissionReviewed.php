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

class NotifyResearchSubmissionReviewed implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(public int $submissionId) {}

    public function handle(): void
    {
        $submission = ResearchRequirementSubmission::with(['assignment.requirement', 'assignment.researchGroup', 'submittedBy'])->find($this->submissionId);
        if (! $submission || ! $submission->submittedBy) {
            logger()->error('NotifyResearchSubmissionReviewed: submission or submitter not found', ['submission_id' => $this->submissionId]);
            return;
        }

        $user = $submission->submittedBy;
        $accepted = $submission->review_status === 'accepted';
        $url = route('faculty-loading.my-research-requirements.index');

        try {
            NotificationService::notifyUser(
                $user,
                'Research Requirement',
                $submission->assignment->requirement->title,
                $accepted ? 'Your submission was accepted.' : 'Your submission was returned for revision.',
                $url,
            );

            if ($user->email) {
                Mail::to($user->email)->send(new ResearchRequirementMail(
                    recipientName: $user->name,
                    headerTitle: $accepted ? 'Submission Accepted' : 'Submission Returned for Revision',
                    lead: $accepted
                        ? "Your submission for \"{$submission->assignment->requirement->title}\" has been accepted."
                        : "Your submission for \"{$submission->assignment->requirement->title}\" needs revision.",
                    details: $accepted ? [] : [['Feedback', $submission->review_comment ?? '—']],
                    actionUrl: $url,
                    actionLabel: 'View My Submissions',
                ));
            }
        } catch (\Throwable $e) {
            logger()->warning('NotifyResearchSubmissionReviewed: notify failed', ['submission_id' => $submission->id, 'error' => $e->getMessage()]);
        }
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('NotifyResearchSubmissionReviewed: job FAILED', ['submission_id' => $this->submissionId, 'error' => $e->getMessage()]);
    }
}
