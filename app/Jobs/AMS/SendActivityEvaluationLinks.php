<?php

namespace App\Jobs\AMS;

use App\Mail\AMS\ActivityEvaluationInviteMail;
use App\Models\AMS\Activity;
use App\Models\AMS\ActivityParticipant;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendActivityEvaluationLinks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // Job upper-bound. MUST stay below the queue connection retry_after
    // (see config/queue.php → REDIS_QUEUE_RETRY_AFTER, 900s) so the job is
    // never re-released to another worker mid-flight while still running.
    public int $timeout = 600;

    // Single attempt — re-running this on a flaky failure would re-send
    // evaluation invite emails to participants who already got one.
    public int $tries = 1;

    // Pass the primitive ID, not the Eloquent model, and the requester's ID
    // separately so we can notify them when the job finishes. Avoids
    // SerializesModels deserialization issues during rolling deploys.
    public function __construct(
        public int $activityId,
        public int $requestedByUserId,
    ) {}

    public function handle(): void
    {
        $activity = Activity::find($this->activityId);

        if (! $activity) {
            logger()->error('SendActivityEvaluationLinks: activity not found', [
                'activity_id' => $this->activityId,
            ]);
            return;
        }

        $participants = ActivityParticipant::where('activity_id', $activity->id)
            ->where('participant_type', 'employee')
            ->where('attended', 'yes')
            ->get();

        $sent   = 0;
        $failed = 0;

        foreach ($participants as $p) {
            $user = User::find($p->participant_id);
            if (! $user?->email) {
                $failed++;
                continue;
            }

            $hash          = md5($p->participant_id.'-'.$activity->id);
            $evaluationUrl = route('ams.activities.evaluate.show', [$activity->id, $hash]);

            try {
                Mail::to($user->email)->send(
                    new ActivityEvaluationInviteMail($activity, $user->name, $evaluationUrl)
                );
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                logger()->warning("AMS: evaluation invite failed for {$user->email}: ".$e->getMessage());
                report($e);
            }
        }

        $message = "Evaluation links sent to {$sent} participant(s).";
        if ($failed) {
            $message .= " {$failed} could not be sent (no email or delivery error).";
        }

        logger()->info('SendActivityEvaluationLinks: complete', [
            'activity_id' => $activity->id,
            'sent'        => $sent,
            'failed'      => $failed,
        ]);

        $this->notifyRequester($activity, $message);
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('SendActivityEvaluationLinks: job FAILED', [
            'activity_id' => $this->activityId,
            'error'       => $e->getMessage(),
            'trace'       => $e->getTraceAsString(),
        ]);

        $activity = Activity::find($this->activityId);
        if ($activity) {
            $this->notifyRequester(
                $activity,
                'Sending evaluation links failed unexpectedly. Please try again or contact ICT.'
            );
        }
    }

    private function notifyRequester(Activity $activity, string $message): void
    {
        $requester = User::find($this->requestedByUserId);
        if (! $requester) {
            return;
        }

        try {
            NotificationService::notifyUser(
                $requester,
                'Activity Evaluation Links',
                $activity->title,
                $message,
                route('ams.activities.show', $activity->id),
            );
        } catch (\Throwable $e) {
            logger()->warning('SendActivityEvaluationLinks: requester notification failed', [
                'activity_id' => $activity->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
