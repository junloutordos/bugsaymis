<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\DynaGoogleClientFactory;
use Google\Client as GoogleClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DynaGoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_in_an_existing_user_with_dyna_access_via_verified_google_token(): void
    {
        $user = $this->userWithPermissions(['atlas.dyna.access']);
        $this->mockGoogleClient(['email' => $user->email]);

        $response = $this->postJson('/api/dyna/login/google', [
            'id_token' => 'fake-token', 'device_name' => 'Dyna.app on MacBook Pro',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user' => ['name', 'email']]);
    }

    public function test_returns_404_when_no_atlas_account_matches_the_google_email(): void
    {
        $this->mockGoogleClient(['email' => 'nobody@example.com']);

        $response = $this->postJson('/api/dyna/login/google', [
            'id_token' => 'fake-token', 'device_name' => 'Dyna.app on MacBook Pro',
        ]);

        $response->assertStatus(404);
    }

    public function test_returns_403_when_the_matched_user_lacks_dyna_access(): void
    {
        $user = User::factory()->create();
        $this->mockGoogleClient(['email' => $user->email]);

        $response = $this->postJson('/api/dyna/login/google', [
            'id_token' => 'fake-token', 'device_name' => 'Dyna.app on MacBook Pro',
        ]);

        $response->assertStatus(403);
    }

    public function test_returns_401_when_the_token_fails_verification(): void
    {
        $googleClient = Mockery::mock(GoogleClient::class);
        $googleClient->shouldReceive('verifyIdToken')->once()->andReturn(false);
        $factory = Mockery::mock(DynaGoogleClientFactory::class);
        $factory->shouldReceive('make')->andReturn($googleClient);
        $this->app->instance(DynaGoogleClientFactory::class, $factory);

        $response = $this->postJson('/api/dyna/login/google', [
            'id_token' => 'bad-token', 'device_name' => 'Dyna.app on MacBook Pro',
        ]);

        $response->assertStatus(401);
    }

    private function mockGoogleClient(array $payload): void
    {
        $googleClient = Mockery::mock(GoogleClient::class);
        $googleClient->shouldReceive('verifyIdToken')->once()->andReturn($payload);
        $factory = Mockery::mock(DynaGoogleClientFactory::class);
        $factory->shouldReceive('make')->andReturn($googleClient);
        $this->app->instance(DynaGoogleClientFactory::class, $factory);
    }

    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::create(['name' => 'Dyna Test '.uniqid()]);
        foreach ($permissionNames as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['module' => 'Atlas', 'description' => $name]);
            $role->permissions()->attach($permission);
        }
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
