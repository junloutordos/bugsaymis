<?php

namespace Tests\Feature;

use App\Models\Permission;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PmV2PermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_all_pm_v2_permissions(): void
    {
        $this->seed(PermissionsSeeder::class);

        foreach (['ipcr.v2.view', 'ipcr.v2.create', 'ipcr.v2.update', 'ipcr.v2.submit', 'ipcr.v2.approve', 'ipcr.v2.admin'] as $name) {
            $this->assertTrue(Permission::where('name', $name)->exists(), "Missing permission: {$name}");
        }
    }
}
