<?php

namespace App\Http\Controllers\AMS;

use App\Http\Controllers\Controller;
use App\Models\AMS\Activity;
use App\Models\AMS\ActivityEvaluation;
use App\Models\AMS\ActivityParticipant;
use App\Models\AMS\ActivityStudentAttendance;
use App\Models\Student;
use App\Models\User;
use App\Services\AMS\CertificateService;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller
{
    public function __construct(private CertificateService $certService) {}
    /**
     * Generate certificates for all 'present' participants of an activity.
     * Saves each PDF to storage and updates certificate_path on the participant row.
     */
    public function generate(Activity $activity)
    {
        $activity->load('participants');

        $generated = 0;
        $skipped   = 0;

        // Pre-load evaluations for this activity
        $evaluations = ActivityEvaluation::where('activity_id', $activity->id)
            ->get(['participant_type', 'participant_id'])
            ->keyBy(fn($e) => $e->participant_type . ':' . $e->participant_id);

        foreach ($activity->participants as $participant) {
            if ($participant->attended !== 'yes') {
                $skipped++;
                continue;
            }

            if ($participant->participant_type === 'employee') {
                // Skip if participant has not yet evaluated the activity
                if (!isset($evaluations['employee:' . $participant->participant_id])) {
                    $skipped++;
                    continue;
                }

                $user = User::find($participant->participant_id);
                if (!$user) { $skipped++; continue; }

                $path = $this->certService->buildAndSave(
                    activity: $activity,
                    name: $user->name,
                    hoursAttended: $participant->hours_attended,
                    participantId: $participant->participant_id
                );

                $participant->update(['certificate_path' => $path]);
                $generated++;
            }

            // Sections: handled via student attendance rows
            if ($participant->participant_type === 'section') {
                $rows = ActivityStudentAttendance::where('activity_id', $activity->id)
                    ->where('attended', 'yes')
                    ->get();

                foreach ($rows as $row) {
                    // Skip students who have not yet evaluated
                    if (!isset($evaluations['student:' . $row->participant_id])) {
                        $skipped++;
                        continue;
                    }

                    $student = Student::find($row->participant_id);
                    if (!$student) continue;

                    $path = $this->certService->buildAndSave(
                        activity: $activity,
                        name: $student->full_name,
                        hoursAttended: $row->hours_attended,
                        participantId: $row->participant_id
                    );

                    $row->update(['certificate_path' => $path]);
                    $generated++;
                }
            }
        }

        return back()->with('success', "Generated {$generated} certificate(s). {$skipped} skipped (absent, not evaluated, or missing).");
    }

    public function downloadParticipant(Activity $activity, ActivityParticipant $participant)
    {
        if (!$participant->certificate_path) abort(404, 'Certificate not yet generated.');

        $evaluated = ActivityEvaluation::where('activity_id', $activity->id)
            ->where('participant_type', 'employee')
            ->where('participant_id', $participant->participant_id)
            ->exists();

        if (!$evaluated) abort(403, 'You must complete the activity evaluation before downloading your certificate.');

        return $this->streamPdf($participant->certificate_path, $activity->title);
    }

    public function downloadStudent(Activity $activity, ActivityStudentAttendance $attendance)
    {
        if (!$attendance->certificate_path) abort(404, 'Certificate not yet generated.');

        $evaluated = ActivityEvaluation::where('activity_id', $activity->id)
            ->where('participant_type', 'student')
            ->where('participant_id', $attendance->participant_id)
            ->exists();

        if (!$evaluated) abort(403, 'You must complete the activity evaluation before downloading your certificate.');

        return $this->streamPdf($attendance->certificate_path, $activity->title);
    }

    /**
     * Public verification page — no auth required.
     */
    public function verify(Activity $activity, string $hash)
    {
        // Look in both participant tables for a certificate whose name hash matches
        $participant = ActivityParticipant::where('activity_id', $activity->id)
            ->whereNotNull('certificate_path')
            ->get()
            ->first(function ($p) use ($hash) {
                return md5($p->participant_id . '-' . $p->activity_id) === $hash;
            });

        $studentRow = null;
        if (!$participant) {
            $studentRow = ActivityStudentAttendance::where('activity_id', $activity->id)
                ->whereNotNull('certificate_path')
                ->get()
                ->first(function ($r) use ($hash) {
                    return md5($r->participant_id . '-' . $r->activity_id) === $hash;
                });
        }

        if (!$participant && !$studentRow) {
            abort(404, 'Certificate not found or has not been generated yet.');
        }

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
        $content = Storage::disk('public')->get($storagePath);
        $filename = 'Certificate_' . str_replace(' ', '_', $activityTitle) . '.pdf';

        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
