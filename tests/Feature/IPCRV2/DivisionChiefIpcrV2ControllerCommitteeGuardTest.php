<?php

namespace Tests\Feature\IPCRV2;

use App\Models\Committee;
use App\Models\Division;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PerformanceManagement\CommitteeIpcrSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DivisionChiefIpcrV2ControllerCommitteeGuardTest extends TestCase
{
    use RefreshDatabase;

    private function divisionChiefRole(): Role
    {
        $role = Role::create(['name' => 'DivisionChief_' . uniqid()]);
        $ids = collect(['ipcr.v2.view', 'ipcr.v2.approve', 'ipcr.v2.monitor'])
            ->map(fn ($name) => Permission::firstOrCreate(['name' => $name], ['module' => 'IPCR V2', 'description' => 'x'])->id);
        $role->permissions()->attach($ids);

        return $role;
    }

    public function test_division_chief_cannot_rate_a_committee_sourced_support_item(): void
    {
        $sy = SchoolYear::create(['name' => '2026-2027', 'is_current' => true, 'start_date' => '2026-06-01', 'end_date' => '2027-03-31']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => 'Full Term', 'term_type' => 'full_term', 'is_current' => true]);
        $role = $this->divisionChiefRole();
        $dc = User::factory()->create();
        $dc->roles()->attach($role->id);
        $division = Division::create(['division_name' => 'CID', 'acronym' => 'CID', 'division_chief_id' => $dc->id]);
        $member = User::factory()->create(['division_id' => $division->id]);
        $committee = Committee::create(['name' => 'Grievance Committee']);
        FacultyCommitteeAssignment::create([
            'user_id' => $member->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'committee_id' => $committee->id, 'committee_name' => $committee->name, 'role' => 'member', 'status' => 'active',
        ]);
        $period = IPCRRatingPeriod::create(['label' => 'FY2026-1', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
        $record = IpcrV2Record::create(['user_id' => $member->id, 'rating_period_id' => $period->id]);
        (new CommitteeIpcrSyncService())->syncForUser($member);
        $item = $record->fresh()->supportItems()->first();

        $response = $this->actingAs($dc)->put(route('division-chief-ipcr-v2.rateSupportItem', ['id' => $record->id, 'supportItem' => $item->id]), [
            'quality_rating' => 5, 'efficiency_rating' => 5, 'timeliness_rating' => 5,
        ]);

        $response->assertForbidden();
    }
}
