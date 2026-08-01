<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Atlas\DynaConversation;
use App\Models\Atlas\DynaMessage;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynaConversationShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_the_conversations_messages_in_order(): void
    {
        $user = $this->userWithPermissions(['atlas.dyna.access']);
        $conversation = DynaConversation::create(['user_id' => $user->id, 'title' => 'Leave trends']);
        DynaMessage::create(['dyna_conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'first', 'created_at' => now()]);
        DynaMessage::create(['dyna_conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'second', 'created_at' => now()->addMinute()]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/dyna/conversations/{$conversation->id}");

        $response->assertOk()
            ->assertJsonPath('title', 'Leave trends')
            ->assertJsonPath('messages.0.content', 'first')
            ->assertJsonPath('messages.1.content', 'second');
    }

    public function test_show_rejects_a_conversation_owned_by_another_user(): void
    {
        $owner = $this->userWithPermissions(['atlas.dyna.access']);
        $conversation = DynaConversation::create(['user_id' => $owner->id]);
        $intruder = $this->userWithPermissions(['atlas.dyna.access']);

        $response = $this->actingAs($intruder, 'sanctum')
            ->getJson("/api/dyna/conversations/{$conversation->id}");

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
