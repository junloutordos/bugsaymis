<?php

namespace Tests\Feature\Recruitment;

use App\Models\JobItem;
use App\Models\Permission;
use App\Models\RecruitmentType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobItemPermissionGatingTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermissions(array $permissionNames): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::create(['name' => 'Tmp_' . uniqid()]);

        $permissionIds = collect($permissionNames)->map(
            fn ($name) => Permission::firstOrCreate(['name' => $name], ['module' => 'Recruitment', 'description' => $name])->id
        );

        $role->permissions()->sync($permissionIds);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function recruitmentType(): RecruitmentType
    {
        return RecruitmentType::create([
            'name' => 'Cost of Service (COS) Teacher',
            'is_active' => true,
        ]);
    }

    public function test_view_only_user_gets_redirected_with_flash_error_not_raw_403_page(): void
    {
        $user = $this->userWithPermissions(['recruitment.view']);
        $type = $this->recruitmentType();

        $response = $this->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('recruitment.job-items.store'), [
                'recruitment_type_id' => $type->id,
                'position_title' => 'Should Not Save',
                'daily_rate' => 700,
                'plantilla_numbers' => [],
            ]);

        // Should be a redirect (302) with a flashed error — never a raw 403 render.
        $response->assertStatus(302);
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('job_items', ['position_title' => 'Should Not Save']);
    }

    public function test_manage_user_can_create_job_item(): void
    {
        $user = $this->userWithPermissions(['recruitment.view', 'recruitment.manage']);
        $type = $this->recruitmentType();

        $response = $this->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('recruitment.job-items.store'), [
                'recruitment_type_id' => $type->id,
                'position_title' => 'Should Save',
                'daily_rate' => 700,
                'plantilla_numbers' => [],
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('job_items', ['position_title' => 'Should Save']);
    }

    public function test_index_page_exposes_can_manage_and_can_publish_flags(): void
    {
        $viewOnly = $this->userWithPermissions(['recruitment.view']);

        $response = $this->actingAs($viewOnly)->get(route('recruitment.job-items.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('can.manage', false)
            ->where('can.publish', false)
        );

        $manager = $this->userWithPermissions(['recruitment.view', 'recruitment.manage', 'recruitment.publish']);

        $response2 = $this->actingAs($manager)->get(route('recruitment.job-items.index'));
        $response2->assertOk();
        $response2->assertInertia(fn ($page) => $page
            ->where('can.manage', true)
            ->where('can.publish', true)
        );
    }
}
