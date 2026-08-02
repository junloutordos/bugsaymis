<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DynaCidChiefPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cid_chief_role_is_granted_atlas_dyna_access(): void
    {
        Role::firstOrCreate(['name' => 'CID Chief']);

        $migration = require database_path('migrations/2026_08_03_100000_add_cid_chief_to_dyna_permission.php');
        $migration->up();

        $roleId = DB::table('roles')->where('name', 'CID Chief')->value('id');
        $permissionId = DB::table('permissions')->where('name', 'atlas.dyna.access')->value('id');

        $this->assertDatabaseHas('permission_role', ['role_id' => $roleId, 'permission_id' => $permissionId]);
    }
}
