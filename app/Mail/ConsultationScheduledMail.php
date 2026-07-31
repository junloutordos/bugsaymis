<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ConsultationScheduledMail extends Mailable
{
    use Queueable, SerializesModels;

    public $consult;

    public function __construct($consult)
    {
        $this->consult = $consult;
    }

    public function build()
    {
        $consult = $this->consult;

        $requestor = [
            'name' => $consult->requestor ?? null,
            'sex' => null,
            'grade_level' => null,
            'section' => null,
            'office' => null,
        ];

        if (!empty($consult->requestor_type) && $consult->requestor_type === 'student' && !empty($consult->requestor_id)) {
            $student = DB::table('students')->where('id', $consult->requestor_id)->first();
            if ($student) {
                $requestor['name'] = $student->name ?? $requestor['name'];
                $requestor['sex'] = $student->sex ?? null;

                // resolve current school-year grade/section (falls back to legacy mirror)
                $sectionStudent = app(\App\Services\StudentSectionResolver::class)->latestForStudent($student->id);
                if ($sectionStudent) {
                    $levelId = $sectionStudent->levelid ?? $sectionStudent->level_id ?? null;
                    if ($levelId) {
                        $requestor['grade_level'] = 'Grade ' . $levelId;
                    }
                    $section = DB::table('sections')->where('id', $sectionStudent->sectionid ?? $sectionStudent->section_id ?? null)->first();
                    if ($section) {
                        $sectionName = null;
                        foreach (['sectionname','section_name','name','section'] as $col) {
                            if (isset($section->$col) && $section->$col !== null && $section->$col !== '') {
                                $sectionName = $section->$col;
                                break;
                            }
                        }
                        $requestor['section'] = $sectionName ?? null;
                    }
                }
            }
        } else {
            // non-students -> users table
            if (!empty($consult->requestor_id)) {
                $user = DB::table('users')->where('id', $consult->requestor_id)->first();
                if ($user) {
                    $requestor['name'] = $user->name ?? $requestor['name'];
                    $requestor['sex'] = $user->sex ?? null;
                    $requestor['office'] = $user->office ?? null;
                }
            }
        }

        // scheduled date
        $dateScheduled = null;
        if (!empty($consult->scheduled_at)) {
            $dateScheduled = Carbon::parse($consult->scheduled_at)->toDayDateTimeString();
        }

        return $this->subject('Consultation Appointment Scheduled')
                    ->view('emails.consultation_scheduled')
                    ->with([
                        'consult' => $consult,
                        'requestor' => (object) $requestor,
                        'dateScheduled' => $dateScheduled,
                    ]);
    }
}
