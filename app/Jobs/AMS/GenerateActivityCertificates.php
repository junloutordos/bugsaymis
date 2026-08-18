<?php

namespace App\Jobs\AMS;

use App\Models\AMS\Activity;
use App\Models\AMS\ActivityStudentAttendance;
use App\Models\Student;
use App\Models\User;
use App\Services\AMS\ActivityEvaluationEligibilityService;
use App\Services\AMS\CertificateService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class GenerateActivityCertificates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // Job upper-bound. MUST stay below the queue connection retry_after
    // (see config/queue.php → REDIS_QUEUE_RETRY_AFTER, 900s) so the job is
    // never re-released to another worker mid-flight while still running.
    public int $timeout = 600;

    // Single attempt — re-running PDF generation + emails for everyone on a
    // flaky failure would double-send certificate emails to those who
    // already succeeded. Surface the failure instead of retrying.
    public int $tries = 1;

    // Pass the primitive ID, not the Eloquent model, and the requester's ID
    // separately so we can notify them when the job finishes. Avoids
    // SerializesModels deserialization issues during rolling deploys.
    public function __construct(
        public int $activityId,
        public int $requestedByUserId,
    ) {
        // Loops over every eligible participant (PDF + S3 upload + email
        // each) — keep off 'default' so it never blocks fast single-unit jobs.
        $this->onQueue('bulk');
    }

    public function handle(
        CertificateService $certService,
        ActivityEvaluationEligibilityService $evaluationEligibility,
    ): void {
        $activity = Activity::with('participants')->find($this->activityId);

        if (! $activity) {
            logger()->error('GenerateActivityCertificates: activity not found', [
                'activity_id' => $this->activityId,
            ]);
            return;
        }

        $generated   = 0;
        $skipped     = 0;
        $failedNames = [];

        $evaluations = $evaluationEligibility->evaluatedMap($activity);

        foreach ($activity->participants->where('participant_type', 'employee') as $participant) {
            if ($participant->attended !== 'yes'
                || ! isset($evaluations['employee:'.$participant->participant_id])
                || $participant->certificate_path) {
                $skipped++;
                continue;
            }

            $user = User::find($participant->participant_id);
            if (! $user) {
                $skipped++;
                continue;
            }

            try {
                $path = $certService->buildAndSave(
                    activity: $activity,
                    name: $user->name,
                    hoursAttended: $participant->hours_attended,
                    participantId: $participant->participant_id,
                    participantType: 'employee'
                );

                $participant->update(['certificate_path' => $path]);
                if ($user->email) {
                    $certService->sendCertificateEmail($activity, $user->email, $user->name, $path);
                }
                $generated++;
            } catch (\Throwable $e) {
                $failedNames[] = $user->name;
                logger()->warning("AMS: certificate generation failed for employee {$participant->participant_id}: ".$e->getMessage());
            }
        }

        // Student attendance rows are activity-wide. Process them once rather
        // than once per participating section, which previously duplicated PDFs.
        $studentRows = ActivityStudentAttendance::where('activity_id', $activity->id)->get();
        foreach ($studentRows as $row) {
            if ($row->attended !== 'yes'
                || ! isset($evaluations['student:'.$row->participant_id])
                || $row->certificate_path) {
                $skipped++;
                continue;
            }

            $student = Student::find($row->participant_id);
            if (! $student) {
                $skipped++;
                continue;
            }

            try {
                $path = $certService->buildAndSave(
                    activity: $activity,
                    name: $student->full_name,
                    hoursAttended: $row->hours_attended,
                    participantId: $row->participant_id,
                    participantType: 'student'
                );

                $row->update(['certificate_path' => $path]);
                if ($student->student_email) {
                    $certService->sendCertificateEmail($activity, $student->student_email, $student->full_name, $path);
                }
                $generated++;
            } catch (\Throwable $e) {
                $failedNames[] = $student->full_name;
                logger()->warning("AMS: certificate generation failed for student {$row->participant_id}: ".$e->getMessage());
            }
        }

        $message = "Generated {$generated} eligible certificate(s) and emailed those with an available address. {$skipped} skipped (absent, not evaluated, already generated, or missing).";
        if ($failedNames) {
            $failedCount = count($failedNames);
            $message .= " {$failedCount} failed during generation: ".implode(', ', $failedNames).'.';
        }

        logger()->info('GenerateActivityCertificates: complete', [
            'activity_id' => $activity->id,
            'generated'   => $generated,
            'skipped'     => $skipped,
            'failed'      => count($failedNames),
        ]);

        $this->notifyRequester($activity, $message);
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('GenerateActivityCertificates: job FAILED', [
            'activity_id' => $this->activityId,
            'error'       => $e->getMessage(),
            'trace'       => $e->getTraceAsString(),
        ]);

        $activity = Activity::find($this->activityId);
        if ($activity) {
            $this->notifyRequester(
                $activity,
                'Certificate generation failed unexpectedly. Please try again or contact ICT.'
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
                'Activity Certificates',
                $activity->title,
                $message,
                route('ams.activities.show', $activity->id),
            );
        } catch (\Throwable $e) {
            logger()->warning('GenerateActivityCertificates: requester notification failed', [
                'activity_id' => $activity->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
