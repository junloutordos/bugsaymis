<?php

namespace Tests\Feature\AMS;

use App\Models\AMS\Activity;
use App\Models\AMS\ActivityAttendanceDay;
use App\Models\AMS\ActivityEvaluation;
use App\Models\AMS\ActivityParticipant;
use App\Models\AMS\ActivityStudentAttendance;
use App\Models\FacultyLoading\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\AMS\ActivityReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActivityReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_day_activity_report_has_no_day_columns_and_correct_kpis(): void
    {
        $activity = Activity::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Single Day Report Activity',
            'activity_type' => Activity::TYPE_IN_HOUSE,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
        ]);
        $present = User::factory()->create(['name' => 'Present Employee']);
        $absent  = User::factory()->create(['name' => 'Absent Employee']);
        ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => $present->id,
            'participant_type' => 'employee', 'attended' => 'yes', 'hours_attended' => 8,
            'certificate_path' => 'ams/certificates/present.pdf',
        ]);
        ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => $absent->id,
            'participant_type' => 'employee', 'attended' => 'no', 'hours_attended' => 0,
        ]);
        ActivityEvaluation::create([
            'activity_id' => $activity->id, 'participant_type' => 'employee', 'participant_id' => $present->id,
        ]);

        $report = app(ActivityReportService::class)->buildReport($activity);

        $this->assertSame([], $report['days']);
        $this->assertSame(2, $report['kpis']['invited']);
        $this->assertSame(1, $report['kpis']['present']);
        $this->assertSame(50.0, $report['kpis']['attendance_rate']);
        $this->assertSame(1, $report['kpis']['evaluated']);
        $this->assertSame(1, $report['kpis']['certificates_issued']);

        $presentRow = collect($report['rows'])->firstWhere('name', 'Present Employee');
        $this->assertTrue($presentRow['attended']);
        $this->assertTrue($presentRow['evaluated']);
        $this->assertTrue($presentRow['certificate_issued']);
        $this->assertSame([], $presentRow['daily']);
    }

    public function test_multiday_activity_report_includes_per_day_columns(): void
    {
        $activity = Activity::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Multi-day Report Activity',
            'activity_type' => Activity::TYPE_IN_HOUSE,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-11',
        ]);
        $employee = User::factory()->create(['name' => 'Daily Employee']);
        ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => $employee->id,
            'participant_type' => 'employee', 'attended' => 'yes', 'hours_attended' => 8,
        ]);
        ActivityAttendanceDay::create([
            'activity_id' => $activity->id, 'participant_type' => 'employee',
            'participant_id' => $employee->id, 'date' => '2026-08-10', 'attended' => 'yes', 'hours_attended' => 8,
        ]);
        ActivityAttendanceDay::create([
            'activity_id' => $activity->id, 'participant_type' => 'employee',
            'participant_id' => $employee->id, 'date' => '2026-08-11', 'attended' => 'no', 'hours_attended' => 0,
        ]);

        $report = app(ActivityReportService::class)->buildReport($activity);

        $this->assertSame(['2026-08-10', '2026-08-11'], $report['days']);
        $row = $report['rows'][0];
        $this->assertSame(
            [
                ['date' => '2026-08-10', 'attended' => true],
                ['date' => '2026-08-11', 'attended' => false],
            ],
            $row['daily']
        );
    }

    public function test_student_row_includes_section_label(): void
    {
        $activity = Activity::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Student Section Report Activity',
            'activity_type' => Activity::TYPE_IN_HOUSE,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
        ]);
        $section = Section::create(['sectionname' => 'Newton', 'levelid' => 7, 'is_active' => true]);
        DB::table('section_students')->insert(['sectionid' => $section->id, 'studentid' => 501]);
        Student::forceCreate(['id' => 501, 'firstname' => 'Ada', 'lastname' => 'Lovelace']);
        ActivityStudentAttendance::create([
            'activity_id' => $activity->id, 'participant_id' => 501, 'attended' => 'yes', 'hours_attended' => 8,
        ]);

        $report = app(ActivityReportService::class)->buildReport($activity);

        $studentRow = collect($report['rows'])->firstWhere('type', 'Student');
        $this->assertSame('Grade 7 — Newton', $studentRow['section']);
    }
}
