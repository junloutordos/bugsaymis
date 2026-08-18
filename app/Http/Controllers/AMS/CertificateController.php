<?php

namespace App\Http\Controllers\AMS;

use App\Http\Controllers\Controller;
use App\Jobs\AMS\GenerateActivityCertificates;
use App\Models\AMS\Activity;
use App\Models\AMS\ActivityCoProponent;
use App\Models\AMS\ActivityParticipant;
use App\Models\AMS\ActivityStudentAttendance;
use App\Models\Student;
use App\Models\User;
use App\Services\AMS\ActivityEvaluationEligibilityService;
use App\Services\AMS\CertificateService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller
{
    public function __construct(
        private CertificateService $certService,
        private ActivityEvaluationEligibilityService $evaluationEligibility,
    ) {}
    /**
     * Queue certificate generation for all eligible participants of an activity.
     * The heavy work (PDF render + S3 upload + email) runs in the background;
     * the requester is notified via bell/push when it completes.
     */
    public function generate(Activity $activity)
    {
        $this->authorizeManage($activity);

        GenerateActivityCertificates::dispatch($activity->id, Auth::id());

        return back()->with('success', 'Certificate generation has started. You will be notified once it finishes.');
    }

    public function downloadParticipant(Activity $activity, ActivityParticipant $participant)
    {
        abort_unless(
            $participant->activity_id === $activity->id && $participant->participant_type === 'employee',
            404
        );
        abort_unless($participant->attended === 'yes', 403, 'Certificate is only available to present participants.');
        abort_unless(
            $this->evaluationEligibility->hasEvaluated($activity, 'employee', $participant->participant_id),
            403,
            'You must complete the activity evaluation before downloading your certificate.'
        );
        if (! $participant->certificate_path) {
            abort(404, 'Certificate not yet generated.');
        }

        return $this->streamPdf($participant->certificate_path, $activity->title);
    }

    public function downloadStudent(Activity $activity, ActivityStudentAttendance $attendance)
    {
        abort_unless($attendance->activity_id === $activity->id, 404);
        abort_unless($attendance->attended === 'yes', 403, 'Certificate is only available to present participants.');
        abort_unless(
            $this->evaluationEligibility->hasEvaluated($activity, 'student', $attendance->participant_id),
            403,
            'You must complete the activity evaluation before downloading your certificate.'
        );
        if (! $attendance->certificate_path) {
            abort(404, 'Certificate not yet generated.');
        }

        return $this->streamPdf($attendance->certificate_path, $activity->title);
    }

    /**
     * Public verification page — no auth required.
     */
    public function verify(Activity $activity, string $hash)
    {
        // Look in both participant tables for a certificate whose name hash matches
        $participant = ActivityParticipant::where('activity_id', $activity->id)
            ->where('participant_type', 'employee')
            ->where('attended', 'yes')
            ->whereNotNull('certificate_path')
            ->get()
            ->first(function ($p) use ($hash) {
                return md5($p->participant_id . '-' . $p->activity_id) === $hash;
            });

        $studentRow = null;
        if (!$participant) {
            $studentRow = ActivityStudentAttendance::where('activity_id', $activity->id)
                ->where('attended', 'yes')
                ->whereNotNull('certificate_path')
                ->get()
                ->first(function ($r) use ($hash) {
                    return md5($r->participant_id . '-' . $r->activity_id) === $hash;
                });
        }

        if (!$participant && !$studentRow) {
            abort(404, 'Certificate not found or has not been generated yet.');
        }

        $eligible = $participant
            ? $this->evaluationEligibility->hasEvaluated($activity, 'employee', $participant->participant_id)
            : $this->evaluationEligibility->hasEvaluated($activity, 'student', $studentRow->participant_id);
        abort_unless($eligible, 404, 'Certificate not found or has not been generated yet.');

        // Resolve name
        $name = null;
        if ($participant) {
            $user  = User::find($participant->participant_id);
            $name  = $user?->name ?? "Participant #{$participant->participant_id}";
        } else {
            $student = Student::find($studentRow->participant_id);
            $name    = $student?->full_name ?? "Student #{$studentRow->participant_id}";
        }

        return inertia('AMS/Verify', [
            'activity' => [
                'id'         => $activity->id,
                'title'      => $activity->title,
                'start_date' => $activity->start_date?->toDateString(),
                'end_date'   => $activity->end_date?->toDateString(),
                'venue'      => $activity->venue,
            ],
            'name'      => $name,
            'verified'  => true,
        ]);
    }

    private function streamPdf(string $storagePath, string $activityTitle): \Illuminate\Http\Response
    {
        $content = Storage::disk('s3')->get($storagePath);
        $filename = 'Certificate_' . str_replace(' ', '_', $activityTitle) . '.pdf';

        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    private function authorizeManage(Activity $activity): void
    {
        $user = Auth::user();
        if ($user->isSuperAdmin()) {
            return;
        }

        $isOwner = $activity->user_id === $user->id;
        $isCoProponent = ActivityCoProponent::where('activity_id', $activity->id)
            ->where('employee_id', $user->id)
            ->exists();

        abort_unless($isOwner || $isCoProponent, 403);
    }
}
