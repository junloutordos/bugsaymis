<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ClinicKioskConsultationMail extends Mailable
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
                // Prefer explicit lastname, firstname fields when available
                $lastnameCols = ['lastname', 'last_name', 'lname'];
                $firstnameCols = ['firstname', 'first_name', 'fname'];
                $last = null;
                $first = null;
                foreach ($lastnameCols as $c) {
                    if (isset($student->$c) && $student->$c !== null && $student->$c !== '') {
                        $last = $student->$c;
                        break;
                    }
                }
                foreach ($firstnameCols as $c) {
                    if (isset($student->$c) && $student->$c !== null && $student->$c !== '') {
                        $first = $student->$c;
                        break;
                    }
                }

                if ($last || $first) {
                    $nameParts = [];
                    if ($last) $nameParts[] = $last;
                    if ($first) $nameParts[] = $first;
                    $requestor['name'] = $last && $first ? ($last . ', ' . $first) : implode(' ', $nameParts);
                } else {
                    $requestor['name'] = $student->name ?? $requestor['name'];
                }

                $requestor['sex'] = $student->sex ?? null;

                $sectionStudent = DB::table('section_students')->where('studentid', $student->id)->orderByDesc('id')->first();
                if ($sectionStudent) {
                    $levelId = $sectionStudent->levelid ?? null;
                    if ($levelId) {
                        $requestor['grade_level'] = 'Grade ' . $levelId;
                    }
                    $section = DB::table('sections')->where('id', $sectionStudent->sectionid)->first();
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
            if (!empty($consult->requestor_id)) {
                $user = DB::table('users')->where('id', $consult->requestor_id)->first();
                if ($user) {
                    $requestor['name'] = $user->name ?? $requestor['name'];
                    $requestor['sex'] = $user->sex ?? null;
                    $requestor['office'] = $user->office ?? null;
                }
            }
        }

        $dateScheduled = null;
        if (!empty($consult->scheduled_at)) {
            $dateScheduled = Carbon::parse($consult->scheduled_at)->toDayDateTimeString();
        }

        return $this->subject('New Clinic Consultation Logged')
                    ->view('emails.clinic.consultation_logged')
                    ->with([
                        'consult' => $consult,
                        'requestor' => (object) $requestor,
                        'dateScheduled' => $dateScheduled,
                    ]);
    }
}
