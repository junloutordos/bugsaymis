<?php

namespace Tests\Feature\SPMS;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SPMS\Ipcr;
use App\Models\SPMS\IpcrTarget;
use App\Models\SPMS\MovChecklistItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MovChecklistControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_their_own_mov_file(): void
    {
        Storage::fake('s3');
        $role = Role::create(['name' => 'Faculty']);
        $permission = Permission::create(['name' => 'spms.ipcr.manage', 'module' => 'SPMS']);
        $role->permissions()->attach($permission->id);
        $owner = User::factory()->create();
        $owner->roles()->attach($role->id);

        $ipcr = Ipcr::factory()->create(['user_id' => $owner->id]);
        $target = IpcrTarget::factory()->create(['ipcr_id' => $ipcr->id]);
        Storage::disk('s3')->put('spms/ipcr-mov/test.png', 'fake-image-bytes');
        $item = MovChecklistItem::factory()->create([
            'spms_ipcr_target_id' => $target->id,
            's3_key' => 'spms/ipcr-mov/test.png',
            'status' => 'submitted',
        ]);

        $this->actingAs($owner)->get("/spms/ipcr/mov/{$item->id}")->assertOk();
    }

    public function test_other_employee_cannot_view_someone_elses_mov_file(): void
    {
        Storage::fake('s3');
        $role = Role::create(['name' => 'Faculty']);
        $permission = Permission::create(['name' => 'spms.ipcr.manage', 'module' => 'SPMS']);
        $role->permissions()->attach($permission->id);
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $intruder->roles()->attach($role->id);

        $ipcr = Ipcr::factory()->create(['user_id' => $owner->id]);
        $target = IpcrTarget::factory()->create(['ipcr_id' => $ipcr->id]);
        Storage::disk('s3')->put('spms/ipcr-mov/test.png', 'fake-image-bytes');
        $item = MovChecklistItem::factory()->create([
            'spms_ipcr_target_id' => $target->id,
            's3_key' => 'spms/ipcr-mov/test.png',
            'status' => 'submitted',
        ]);

        $this->actingAs($intruder)->get("/spms/ipcr/mov/{$item->id}")->assertForbidden();
    }
}
