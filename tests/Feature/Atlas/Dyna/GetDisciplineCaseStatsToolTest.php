<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Discipline\DisciplineCase;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetDisciplineCaseStatsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetDisciplineCaseStatsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregate_mode_returns_counts_by_status(): void
    {
        // case_no (unique) and filer_id are required (no default) — confirmed via
        // database/migrations/*_create_discipline_cases_table.php. Everything else used
        // below (incident_date, school_year_id, nature_of_offense, threat_level) is nullable
        // there, but supplied anyway since the tool groups by them.
        $filer = User::factory()->create();

        DisciplineCase::create(['case_no' => 'DISC-2026-07-001', 'student_id' => 1, 'filer_id' => $filer->id, 'status' => 'resolved', 'threat_level' => 'low', 'nature_of_offense' => 'Tardiness', 'incident_date' => '2026-07-01', 'school_year_id' => 1]);
        DisciplineCase::create(['case_no' => 'DISC-2026-07-002', 'student_id' => 2, 'filer_id' => $filer->id, 'status' => 'under_review', 'threat_level' => 'medium', 'nature_of_offense' => 'Bullying', 'incident_date' => '2026-07-05', 'school_year_id' => 1]);

        $user = $this->userWithPermissions(['atlas.dyna.access', 'discipline.view']);

        $result = (new GetDisciplineCaseStatsTool())->execute($user, []);

        $this->assertEquals(['resolved' => 1, 'under_review' => 1], $result['byStatus']);
    }

    public function test_individual_mode_returns_that_students_cases(): void
    {
        // students is a legacy MyISAM table -- RefreshDatabase can't roll it back between
        // tests (per the pattern in tests/Feature/Atlas/WorkspaceSyncTest.php), so use a
        // name unique to this test.
        $lastname = 'DisciplineLookup'.uniqid();
        $studentId = \DB::table('students')->insertGetId([
            'lastname' => $lastname, 'firstname' => 'Test',
        ]);

        $filer = User::factory()->create();
        DisciplineCase::create(['case_no' => 'DISC-2026-07-'.uniqid(), 'student_id' => $studentId, 'filer_id' => $filer->id, 'status' => 'resolved', 'threat_level' => 'low', 'nature_of_offense' => 'Tardiness', 'incident_date' => '2026-07-01', 'school_year_id' => 1]);

        $user = $this->userWithPermissions(['atlas.dyna.access', 'discipline.view']);

        $result = (new GetDisciplineCaseStatsTool())->execute($user, ['student_identifier' => $lastname]);

        $this->assertCount(1, $result['cases']);
        $this->assertEquals('Tardiness', $result['cases'][0]['nature_of_offense']);
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
