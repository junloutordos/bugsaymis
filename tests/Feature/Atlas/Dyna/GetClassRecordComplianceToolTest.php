<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\GradingOption;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetClassRecordComplianceTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Atlas\Dyna\Concerns\AssertsJsonSafeToolResult;
use Tests\TestCase;

class GetClassRecordComplianceToolTest extends TestCase
{
    use AssertsJsonSafeToolResult;
    use RefreshDatabase;

    public function test_administrator_sees_campus_wide_status_breakdown(): void
    {
        $teacher = User::factory()->create();
        // class_records.grading_option_id and .school_year are required (no default) —
        // confirmed via database/migrations/*_create_class_records_table.php. school_year_id
        // (a later-added FK) is nullable, unlike school_year itself.
        $gradingOption = GradingOption::create(['name' => 'Standard']);
        ClassRecord::create(['teacher_id' => $teacher->id, 'grading_option_id' => $gradingOption->id, 'school_year' => '2026-2027', 'status' => 'checked', 'subject_name' => 'Math', 'year_level_section' => 'G7-A']);
        ClassRecord::create(['teacher_id' => $teacher->id, 'grading_option_id' => $gradingOption->id, 'school_year' => '2026-2027', 'status' => 'draft', 'subject_name' => 'Science', 'year_level_section' => 'G7-A']);

        $administrator = User::factory()->create();
        $administrator->roles()->attach(Role::firstOrCreate(['name' => 'Administrator']));

        $result = (new GetClassRecordComplianceTool())->execute($administrator, []);

        $this->assertEquals(['checked' => 1, 'draft' => 1], $result);
    }

    public function test_result_contains_no_non_scalar_leaked_date_objects(): void
    {
        $teacher = User::factory()->create();
        $gradingOption = GradingOption::create(['name' => 'Standard']);
        ClassRecord::create(['teacher_id' => $teacher->id, 'grading_option_id' => $gradingOption->id, 'school_year' => '2026-2027', 'status' => 'checked', 'subject_name' => 'Math', 'year_level_section' => 'G7-A']);

        $administrator = User::factory()->create();
        $administrator->roles()->attach(Role::firstOrCreate(['name' => 'Administrator']));

        $result = (new GetClassRecordComplianceTool())->execute($administrator, []);

        $this->assertNoNonScalarLeaves($result);
    }
}
