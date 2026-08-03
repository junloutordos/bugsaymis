<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\TeacherTapLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetTeacherAttendanceStatsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Atlas\Dyna\Concerns\AssertsJsonSafeToolResult;
use Tests\TestCase;

class GetTeacherAttendanceStatsToolTest extends TestCase
{
    use AssertsJsonSafeToolResult;
    use RefreshDatabase;

    public function test_administrator_sees_campus_wide_tap_status_breakdown(): void
    {
        $teacher = User::factory()->create();
        // teacher_tap_logs.classroom_id is a required FK (no default) — confirmed via
        // database/migrations/*_create_teacher_tap_logs_table.php. classrooms.school_year_id
        // is also required (made non-nullable by a later migration) — confirmed via
        // database/migrations/*_add_school_year_id_to_classrooms.php.
        $schoolYear = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        $classroom = Classroom::create(['name' => 'Science Hall 101', 'code' => 'SH-101-'.uniqid(), 'school_year_id' => $schoolYear->id]);
        TeacherTapLog::create(['user_id' => $teacher->id, 'classroom_id' => $classroom->id, 'status' => 'on_time', 'tapped_at' => now(), 'is_late' => false]);
        TeacherTapLog::create(['user_id' => $teacher->id, 'classroom_id' => $classroom->id, 'status' => 'late', 'tapped_at' => now()->addMinute(), 'is_late' => true, 'late_minutes' => 12]);
        TeacherTapLog::create(['user_id' => $teacher->id, 'classroom_id' => $classroom->id, 'status' => 'no_match', 'tapped_at' => now()->addMinutes(2), 'is_late' => false]);

        $administrator = User::factory()->create();
        $administrator->roles()->attach(Role::firstOrCreate(['name' => 'Administrator']));

        $result = (new GetTeacherAttendanceStatsTool())->execute($administrator, []);

        $this->assertEquals(['on_time' => 1, 'late' => 1, 'no_match' => 1], $result);
    }

    public function test_result_contains_no_non_scalar_leaked_date_objects(): void
    {
        $teacher = User::factory()->create();
        $schoolYear = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        $classroom = Classroom::create(['name' => 'Science Hall 101', 'code' => 'SH-101-'.uniqid(), 'school_year_id' => $schoolYear->id]);
        TeacherTapLog::create(['user_id' => $teacher->id, 'classroom_id' => $classroom->id, 'status' => 'on_time', 'tapped_at' => now(), 'is_late' => false]);

        $administrator = User::factory()->create();
        $administrator->roles()->attach(Role::firstOrCreate(['name' => 'Administrator']));

        $result = (new GetTeacherAttendanceStatsTool())->execute($administrator, []);

        $this->assertNoNonScalarLeaves($result);
    }
}
