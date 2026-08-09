<?php

namespace Tests\Feature\Learn;

use App\Models\Learn\RubricTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RubricTemplateModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_belongs_to_user_and_has_ordered_criteria(): void
    {
        $user = User::factory()->create();
        $template = RubricTemplate::create(['user_id' => $user->id, 'name' => 'Essay Rubric']);
        $template->criteria()->create(['description' => 'Content', 'max_points' => 20, 'position' => 1]);
        $template->criteria()->create(['description' => 'Grammar', 'max_points' => 10, 'position' => 0]);

        $this->assertTrue($template->user->is($user));
        $this->assertSame(['Grammar', 'Content'], $template->fresh()->criteria->pluck('description')->all());
    }
}
