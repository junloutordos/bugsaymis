<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\HomeroomAttendance\MonthlyReport;
use App\Models\HomeroomAttendance\MonthlyReportLine;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetHomeroomAttendanceSummaryTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetHomeroomAttendanceSummaryToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregate_mode_returns_campus_wide_averages(): void
    {
        // section_id is an unconstrained legacy-table FK (any int works); school_year_id
        // is a real constrained FK to school_years, per
        // database/migrations/2026_07_28_160500_create_homeroom_monthly_reports_table.php.
        $schoolYear = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        $report = MonthlyReport::create(['section_id' => 1, 'school_year_id' => $schoolYear->id, 'month' => 7, 'year' => 2026]);
        MonthlyReportLine::create(['homeroom_monthly_report_id' => $report->id, 'student_id' => 1, 'cutting_count' => 2, 'is_perfect_attendance' => false, 'excused_absences' => 1, 'unexcused_absences' => 1]);
        MonthlyReportLine::create(['homeroom_monthly_report_id' => $report->id, 'student_id' => 2, 'cutting_count' => 0, 'is_perfect_attendance' => true, 'excused_absences' => 0, 'unexcused_absences' => 0]);

        $user = $this->userWithPermissions(['atlas.dyna.access', 'homeroom-attendance.admin']);

        $result = (new GetHomeroomAttendanceSummaryTool())->execute($user, []);

        $this->assertEquals(1, $result['perfect_attendance_count']);
        $this->assertEquals(1.0, $result['average_cutting_count']);
    }

    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::create(['name' => 'Dyna Test '.uniqid()]);
        foreach ($permissionNames as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['module' => 'Atlas', 'description' => $name]);
            $role->permissions()->attach($permission);
        }
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
