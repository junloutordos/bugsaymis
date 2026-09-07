<?php

namespace Tests\Feature\IPCRV2;

use App\Models\EmployeeFunction;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\IPCRV2\IpcrV2PdfService;
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

    public function test_html_shows_real_success_indicator_and_merges_multi_plan_function_rows(): void
    {
        $employee = User::factory()->create();
        $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = IpcrV2Record::create(['user_id' => $employee->id, 'rating_period_id' => $period->id]);
        $coreFunction = EmployeeFunction::create(['user_id' => $employee->id, 'function_type' => 'core', 'source_type' => 'wdp', 'label' => 'IT Management', 'weight_percent' => 100]);

        $record->coreItems()->create([
            'employee_function_id' => $coreFunction->id, 'label' => 'IT Management', 'weight_percent' => 50,
            'success_indicator' => 'Deliver IT support within SLA', 'mov_link' => 'https://drive.example/report.pdf',
        ]);
        $record->coreItems()->create([
            'employee_function_id' => $coreFunction->id, 'label' => 'IT Management', 'weight_percent' => 50,
            'success_indicator' => 'Job requests completed on time',
        ]);
        $record->supportItems()->create([
            'label' => 'Administrative', 'success_indicator' => 'DTR submitted on time', 'target' => '100% compliance',
        ]);

        $html = (new IpcrV2PdfService())->renderHtml($record->fresh(['coreItems', 'supportItems', 'user', 'period']));

        $this->assertStringContainsString('Deliver IT support within SLA', $html);
        $this->assertStringContainsString('Job requests completed on time', $html);
        $this->assertStringContainsString('MOV: https://drive.example/report.pdf', $html);
        $this->assertStringContainsString('DTR submitted on time', $html);
        $this->assertStringContainsString('100% compliance', $html);
        $this->assertStringNotContainsString('100% delivered', $html);
        $this->assertStringContainsString('rowspan="2">IT Management', $html);
        $this->assertStringContainsString('Discussed with', $html);
        $this->assertStringContainsString('Assessed by', $html);
        $this->assertStringContainsString('Final Rating by', $html);
    }
}
