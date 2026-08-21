<?php

namespace Tests\Feature\Sos;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SosChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // .env.testing has no BROADCAST_CONNECTION, so it defaults to the
        // 'null' driver, which trivially authorizes every channel and would
        // make this test pass regardless of the actual channel definition.
        // Broadcast::channel() registrations attach to whichever driver is
        // default AT BOOT TIME — overriding config() from inside a test
        // (after boot) creates a second, unregistered driver instance and
        // silently no-ops. Must set the real env var BEFORE the app boots
        // (putenv() here runs before parent::setUp()'s createApplication(),
        // and Dotenv's default immutable loader won't override an
        // already-set env var), so routes/channels.php registers against
        // the real pusher driver from the start. HMAC auth signing is a
        // local computation — no network call to a live Pusher/Soketi server.
        putenv('BROADCAST_CONNECTION=pusher');
        putenv('PUSHER_APP_KEY=test-key');
        putenv('PUSHER_APP_SECRET=test-secret');
        putenv('PUSHER_APP_ID=test-app-id');

        parent::setUp();
    }

    protected function tearDown(): void
    {
        putenv('BROADCAST_CONNECTION');
        putenv('PUSHER_APP_KEY');
        putenv('PUSHER_APP_SECRET');
        putenv('PUSHER_APP_ID');

        parent::tearDown();
    }

    public function test_user_with_sos_respond_permission_can_join_channel(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'sos.respond'], ['module' => 'SOS', 'description' => 'x']);
        $role = Role::firstOrCreate(['name' => 'DRRM Coordinator']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->actingAs($user);

        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-sos-responders',
            'socket_id' => '1234.5678',
        ]);

        $response->assertOk();
    }

    public function test_user_without_permission_is_denied(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-sos-responders',
            'socket_id' => '1234.5678',
        ]);

        $response->assertForbidden();
    }
}
