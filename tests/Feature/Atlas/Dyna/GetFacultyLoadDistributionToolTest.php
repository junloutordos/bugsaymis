<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Division;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetFacultyLoadDistributionTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetFacultyLoadDistributionToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_load_status_distribution_scoped_to_division_chiefs_division(): void
    {
        $divisionA = Division::factory()->create();
        $divisionB = Division::factory()->create();
        $facultyA1 = User::factory()->create(['division_id' => $divisionA->id]);
        $facultyA2 = User::factory()->create(['division_id' => $divisionA->id]);
        $facultyB1 = User::factory()->create(['division_id' => $divisionB->id]);

        // faculty_loads.school_year_id and .academic_term_id are required FKs (no default) —
        // confirmed via database/migrations/*_create_faculty_loads_table.php. school_years
        // also requires start_date/end_date (no default) — confirmed via
        // database/migrations/*_create_school_years_table.php.
        $schoolYear = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        $term = AcademicTerm::create(['school_year_id' => $schoolYear->id, 'name' => '1st Semester']);

        FacultyLoad::create(['user_id' => $facultyA1->id, 'school_year_id' => $schoolYear->id, 'academic_term_id' => $term->id, 'load_status' => 'overload', 'total_units' => 20]);
        FacultyLoad::create(['user_id' => $facultyA2->id, 'school_year_id' => $schoolYear->id, 'academic_term_id' => $term->id, 'load_status' => 'full_load', 'total_units' => 18]);
        FacultyLoad::create(['user_id' => $facultyB1->id, 'school_year_id' => $schoolYear->id, 'academic_term_id' => $term->id, 'load_status' => 'underload', 'total_units' => 10]);

        $chief = User::factory()->create(['division_id' => $divisionA->id]);
        $divisionA->update(['division_chief_id' => $chief->id]);
        $chief->roles()->attach(Role::firstOrCreate(['name' => 'DivisionChief']));

        $result = (new GetFacultyLoadDistributionTool())->execute($chief, []);

        $this->assertEquals(['overload' => 1, 'full_load' => 1], $result);
    }
}
