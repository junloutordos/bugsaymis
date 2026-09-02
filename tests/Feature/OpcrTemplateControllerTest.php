<?php

namespace Tests\Feature;

use App\Models\PM2\IpcrRatingPeriodV2;
use App\Models\PM2\OpcrTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'ipcr.v2.admin'], ['module' => 'PM V2', 'description' => 'ipcr.v2.admin']);
        $role = Role::create(['name' => 'PmV2Admin_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_admin_can_create_and_list_template_items_for_the_current_period(): void
    {
        $user = $this->adminUser();
        $period = IpcrRatingPeriodV2::create(['label' => 'Jul-Dec 2026', 'year' => 2026, 'is_current' => true]);

        $response = $this->actingAs($user)->post(route('pm2.opcr-templates.storeItem'), [
            'strategy_label'   => 'Strategy 1',
            'output_outcome'   => 'STEM secondary education strengthened',
            'success_indicator'=> '% of graduates admitted to STEM programs',
            'target'           => '95%',
            'weight_percent'   => 30,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('opcr_template_items', ['strategy_label' => 'Strategy 1', 'weight_percent' => 30]);

        $indexResponse = $this->actingAs($user)->get(route('pm2.opcr-templates.index'));
        $indexResponse->assertInertia(fn ($page) => $page
            ->where('template.items.0.strategy_label', 'Strategy 1')
        );
    }

    public function test_non_admin_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('pm2.opcr-templates.index'))->assertForbidden();
    }
}
