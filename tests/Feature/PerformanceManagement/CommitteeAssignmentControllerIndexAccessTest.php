<?php

namespace Tests\Feature\PerformanceManagement;

use App\Models\Committee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeAssignmentControllerIndexAccessTest extends TestCase
{
    use RefreshDatabase;

    private function withAccomplishmentsView(User $user): User
    {
        $role = Role::create(['name' => 'TestRole_' . uniqid()]);
        $perm = Permission::firstOrCreate(['name' => 'accomplishments.view'], ['module' => 'Accomplishments', 'description' => 'accomplishments.view']);
        $role->permissions()->attach($perm->id);
        $user->roles()->attach($role->id);

        return $user;
    }

    /**
     * PMS's original CommitteePerformanceController::index() had no
     * authorize() call at all — any authenticated user could load it, with
     * the catalog scoped to their own committees. The merge with FL's
     * index() (which was faculty_loading.manage-only) must not regress that:
     * a plain accomplishments.view holder with no faculty_loading.manage
     * must still get a 200, not a 403.
     */
    public function test_an_accomplishments_view_holder_without_manage_permission_can_load_the_index(): void
    {
        $user = $this->withAccomplishmentsView(User::factory()->create());
        $committee = Committee::create(['name' => 'Grievance Committee', 'head_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('pm-committees.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('canManage', false)
            ->has('catalog', 1)
            ->where('catalog.0.id', $committee->id)
        );
    }

    /**
     * The same non-admin viewer must not see a committee they have no
     * relationship to — catalog visibility stays scoped, matching PMS's
     * original per-user filter.
     */
    public function test_an_accomplishments_view_holder_does_not_see_unrelated_committees(): void
    {
        $user = $this->withAccomplishmentsView(User::factory()->create());
        Committee::create(['name' => 'Unrelated Committee']);

        $response = $this->actingAs($user)->get(route('pm-committees.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('catalog', 0));
    }
}
