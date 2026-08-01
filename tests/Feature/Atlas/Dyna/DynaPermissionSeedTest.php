<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DynaPermissionSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_dyna_permission_is_granted_to_administrator_ocd_and_division_chief(): void
    {
        // RefreshDatabase runs every migration (including this one) against an empty roles
        // table, so the migration's grants may have matched nothing at that point. Seed the
        // target roles now, then re-run this migration's up() directly — idempotent
        // (updateOrInsert/insertOrIgnore) — to exercise its grant logic against a
        // roles-already-exist state, which is how it actually runs in production.
        foreach (['Administrator', 'OCD', 'DivisionChief'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        $migration = require database_path('migrations/2026_08_03_090100_seed_dyna_permission.php');
        $migration->up();

        $this->assertDatabaseHas('permissions', ['name' => 'atlas.dyna.access']);

        foreach (['Administrator', 'OCD', 'DivisionChief'] as $roleName) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            $permissionId = DB::table('permissions')->where('name', 'atlas.dyna.access')->value('id');

            $this->assertDatabaseHas('permission_role', [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }
}
