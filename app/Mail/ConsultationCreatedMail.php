<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConsultationCreatedMail extends Mailable
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
            $student = \Illuminate\Support\Facades\DB::table('students')->where('id', $consult->requestor_id)->first();
            if ($student) {
                // Build name from lastname, firstname when possible
                $lastnameCols = ['lastname', 'last_name', 'lname'];
                $firstnameCols = ['firstname', 'first_name', 'fname'];
                $last = null; $first = null;
                foreach ($lastnameCols as $c) { if (isset($student->$c) && $student->$c) { $last = $student->$c; break; } }
                foreach ($firstnameCols as $c) { if (isset($student->$c) && $student->$c) { $first = $student->$c; break; } }
                if ($last || $first) {
                    $requestor['name'] = $last && $first ? ($last . ', ' . $first) : trim(($last ?? '') . ' ' . ($first ?? ''));
                } else {
                    $requestor['name'] = $student->name ?? $requestor['name'];
                }
                $requestor['sex'] = $student->sex ?? null;

                $sectionStudent = app(\App\Services\StudentSectionResolver::class)->latestForStudent($student->id);
                if ($sectionStudent) {
                    $levelId = $sectionStudent->levelid ?? ($sectionStudent->level_id ?? null);
                    if ($levelId) {
                        $requestor['grade_level'] = 'Grade ' . $levelId;
                    }
                    $section = \Illuminate\Support\Facades\DB::table('sections')->where('id', $sectionStudent->sectionid ?? $sectionStudent->section_id ?? null)->first();
                    if ($section) {
                        $sectionName = null;
                        foreach (['sectionname','section_name','name','section'] as $col) {
                            if (isset($section->$col) && $section->$col) { $sectionName = $section->$col; break; }
                        }
                        $requestor['section'] = $sectionName ?? null;
                    }
                }
            }
        } else {
            if (!empty($consult->requestor_id)) {
                $user = \Illuminate\Support\Facades\DB::table('users')->where('id', $consult->requestor_id)->first();
                if ($user) {
                    $requestor['name'] = $user->name ?? $requestor['name'];
                    $requestor['sex'] = $user->sex ?? null;
                    $requestor['office'] = $user->office ?? null;
                }
            }
        }

        $dateScheduled = null;
        if (!empty($consult->schedule_id)) {
            $sched = \Illuminate\Support\Facades\DB::table('physician_schedule')->where('id', $consult->schedule_id)->first();
            if ($sched && !empty($sched->schedule_date)) {
                $date = \Carbon\Carbon::parse($sched->schedule_date)->toFormattedDateString();
                $start = $sched->time_start ?? null;
                $end = $sched->time_end ?? null;
                try { if ($start) $start = \Carbon\Carbon::parse($start)->format('g:i A'); } catch (\Throwable $e) {}
                try { if ($end) $end = \Carbon\Carbon::parse($end)->format('g:i A'); } catch (\Throwable $e) {}
                if ($start && $end) { $date .= ' · ' . $start . ' - ' . $end; }
                elseif ($start) { $date .= ' · ' . $start; }
                $dateScheduled = $date;
            }
        }
        if (!$dateScheduled && !empty($consult->scheduled_at)) {
            $dateScheduled = \Carbon\Carbon::parse($consult->scheduled_at)->toDayDateTimeString();
        }

        return $this->subject('New Consultation Request — Action Required')
                    ->view('emails.consultation_created')
                    ->with([
                        'consult' => $consult,
                        'requestor' => (object) $requestor,
                        'dateScheduled' => $dateScheduled,
                    ]);
    }
}
