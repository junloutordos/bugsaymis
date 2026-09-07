<?php

namespace Tests\Feature\IPCRV2;

use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcrV2PdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_stream_their_own_ipcr_v2_pdf(): void
    {
        $role = Role::create(['name' => 'Faculty']);
        $ids = collect(['ipcr.v2.view'])->map(fn ($name) => Permission::firstOrCreate(['name' => $name], ['module' => 'IPCR V2', 'description' => 'x'])->id);
        $role->permissions()->attach($ids);
        $employee = User::factory()->create();
        $employee->roles()->attach($role->id);

        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = IpcrV2Record::create(['user_id' => $employee->id, 'rating_period_id' => $period->id]);

        $response = $this->actingAs($employee)->get(route('ipcr-v2-pdf.show', $record->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_unrelated_faculty_cannot_stream_another_employees_pdf(): void
    {
        $role = Role::create(['name' => 'Faculty']);
        $ids = collect(['ipcr.v2.view'])->map(fn ($name) => Permission::firstOrCreate(['name' => $name], ['module' => 'IPCR V2', 'description' => 'x'])->id);
        $role->permissions()->attach($ids);
        $viewer = User::factory()->create();
        $viewer->roles()->attach($role->id);

        $other = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = IpcrV2Record::create(['user_id' => $other->id, 'rating_period_id' => $period->id]);

        $this->actingAs($viewer)->get(route('ipcr-v2-pdf.show', $record->id))->assertForbidden();
    }
}
