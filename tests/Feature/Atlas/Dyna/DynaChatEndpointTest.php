<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Atlas\DynaConversation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\DynaOrchestratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynaChatEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_creates_a_conversation_when_none_is_given_and_returns_the_answer(): void
    {
        $user = $this->userWithPermissions(['atlas.dyna.access']);

        $this->mock(DynaOrchestratorService::class, function ($mock) {
            $mock->shouldReceive('reply')
                ->once()
                ->andReturn('There are 42 active employees.');
        });

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/dyna/chat', ['message' => 'How many active employees do we have?']);

        $response->assertOk()->assertJsonStructure(['conversation_id', 'answer']);
        $this->assertDatabaseHas('dyna_conversations', ['user_id' => $user->id]);
    }

    public function test_chat_reuses_an_existing_conversation_owned_by_the_user(): void
    {
        $user = $this->userWithPermissions(['atlas.dyna.access']);
        $conversation = DynaConversation::create(['user_id' => $user->id]);

        $this->mock(DynaOrchestratorService::class, function ($mock) {
            $mock->shouldReceive('reply')->once()->andReturn('Follow-up answer.');
        });

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/dyna/chat', ['conversation_id' => $conversation->id, 'message' => 'And last quarter?']);

        $response->assertOk()->assertJson(['conversation_id' => $conversation->id]);
    }

    public function test_chat_rejects_a_conversation_id_owned_by_another_user(): void
    {
        $owner = $this->userWithPermissions(['atlas.dyna.access']);
        $conversation = DynaConversation::create(['user_id' => $owner->id]);
        $intruder = $this->userWithPermissions(['atlas.dyna.access']);

        $response = $this->actingAs($intruder, 'sanctum')
            ->postJson('/api/dyna/chat', ['conversation_id' => $conversation->id, 'message' => 'hi']);

        $response->assertStatus(404);
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
