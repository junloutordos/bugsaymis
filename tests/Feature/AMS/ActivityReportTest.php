<?php

namespace Tests\Feature\AMS;

use App\Models\AMS\Activity;
use App\Models\AMS\ActivityAttendanceDay;
use App\Models\AMS\ActivityEvaluation;
use App\Models\AMS\ActivityParticipant;
use App\Models\AMS\ActivityStudentAttendance;
use App\Models\FacultyLoading\Section;
use App\Models\Permission;
use App\Models\Role;
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
        // `students`/`section_students` are legacy MyISAM tables (non-transactional) —
        // never hardcode a fixed id here, or repeated test runs collide on a leftover row.
        $student = Student::forceCreate(['firstname' => 'Ada', 'lastname' => 'Lovelace']);
        DB::table('section_students')->insert(['sectionid' => $section->id, 'studentid' => $student->id]);
        ActivityStudentAttendance::create([
            'activity_id' => $activity->id, 'participant_id' => $student->id, 'attended' => 'yes', 'hours_attended' => 8,
        ]);

        try {
            $report = app(ActivityReportService::class)->buildReport($activity);

            $studentRow = collect($report['rows'])->firstWhere('type', 'Student');
            $this->assertSame('Grade 7 — Newton', $studentRow['section']);
        } finally {
            DB::table('section_students')->where('studentid', $student->id)->delete();
            $student->delete();
        }
    }

    public function test_authorized_user_can_view_report_page(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = Activity::create([
            'user_id' => $owner->id, 'title' => 'Report Page Activity',
            'activity_type' => Activity::TYPE_IN_HOUSE, 'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
        ]);

        $this->actingAs($owner)
            ->get(route('ams.activities.report', $activity))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AMS/Report')
                ->where('activity.id', $activity->id)
                ->has('report.kpis')
                ->has('report.rows')
            );
    }

    public function test_print_page_renders_component(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = Activity::create([
            'user_id' => $owner->id, 'title' => 'Print Page Activity',
            'activity_type' => Activity::TYPE_IN_HOUSE, 'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
        ]);

        $this->actingAs($owner)
            ->get(route('ams.activities.report.print', $activity))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('AMS/ReportPrint'));
    }

    public function test_unrelated_user_cannot_view_report_page(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $stranger = $this->userWithPermission('activities.manage');
        $activity = Activity::create([
            'user_id' => $owner->id, 'title' => 'Private Report Activity',
            'activity_type' => Activity::TYPE_IN_HOUSE, 'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
        ]);

        $this->actingAs($stranger)
            ->get(route('ams.activities.report', $activity))
            ->assertForbidden();
    }

    private function userWithPermission(string $permissionName): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['module' => 'Activities', 'description' => $permissionName],
        );
        $role = Role::create(['name' => 'AMS Report Test '.uniqid()]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        return $user;
    }
}
