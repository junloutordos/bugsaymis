<?php

namespace Tests\Feature\Learn;

use App\Models\Learn\RubricTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RubricTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_rename_and_delete_a_template(): void
    {
        $user = User::factory()->create();
        $template = RubricTemplate::create(['user_id' => $user->id, 'name' => 'Original']);

        $this->actingAs($user)
            ->put(route('learn.rubric-templates.update', $template), ['name' => 'Renamed'])
            ->assertRedirect();
        $this->assertSame('Renamed', $template->fresh()->name);

        $this->actingAs($user)
            ->delete(route('learn.rubric-templates.destroy', $template))
            ->assertRedirect();
        $this->assertDatabaseMissing('learn_rubric_templates', ['id' => $template->id]);
    }

    public function test_non_owner_cannot_rename_or_delete(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $template = RubricTemplate::create(['user_id' => $owner->id, 'name' => 'Original']);

        $this->actingAs($stranger)
            ->put(route('learn.rubric-templates.update', $template), ['name' => 'Hacked'])
            ->assertForbidden();
        $this->actingAs($stranger)
            ->delete(route('learn.rubric-templates.destroy', $template))
            ->assertForbidden();

        $this->assertDatabaseHas('learn_rubric_templates', ['id' => $template->id, 'name' => 'Original']);
    }
}
