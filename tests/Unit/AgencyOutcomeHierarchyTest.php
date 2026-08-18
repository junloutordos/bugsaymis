<?php

namespace Tests\Unit;

use App\Models\AgencyOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyOutcomeHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_child_outcome_belongs_to_its_parent(): void
    {
        $parent = AgencyOutcome::create(['outcome' => 'A. STEM Secondary Education', 'function_type' => 'Strategic Functions']);
        $child = AgencyOutcome::create([
            'outcome' => 'A. STEM Secondary Education',
            'sub_outcome' => 'A.1',
            'function_type' => 'Strategic Functions',
            'parent_id' => $parent->id,
        ]);

        $this->assertTrue($child->parent->is($parent));
        $this->assertTrue($parent->children->contains($child));
    }

    public function test_top_level_scope_excludes_children(): void
    {
        $parent = AgencyOutcome::create(['outcome' => 'A. STEM', 'function_type' => 'Strategic Functions']);
        AgencyOutcome::create([
            'outcome' => 'A. STEM', 'sub_outcome' => 'A.1', 'function_type' => 'Strategic Functions', 'parent_id' => $parent->id,
        ]);
        $standalone = AgencyOutcome::create(['outcome' => 'B. Standalone', 'function_type' => 'Core Functions']);

        $topLevelIds = AgencyOutcome::topLevel()->pluck('id')->sort()->values()->toArray();

        $this->assertEquals([$parent->id, $standalone->id], collect($topLevelIds)->sort()->values()->toArray());
    }
}
