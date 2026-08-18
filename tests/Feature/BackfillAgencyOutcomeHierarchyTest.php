<?php

namespace Tests\Feature;

use App\Models\AgencyOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillAgencyOutcomeHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_groups_duplicate_outcome_text_rows_under_a_new_parent(): void
    {
        $rows = collect(['A.1', 'A.2', 'A.3', 'A.4'])->map(fn ($sub) => AgencyOutcome::create([
            'outcome' => 'A. STEM Secondary Education on Scholarship Basis Program',
            'sub_outcome' => $sub,
            'function_type' => 'Strategic Functions',
        ]));

        $this->artisan('agency-outcomes:backfill-hierarchy')->assertSuccessful();

        $parent = AgencyOutcome::whereNull('parent_id')
            ->where('outcome', 'A. STEM Secondary Education on Scholarship Basis Program')
            ->first();

        $this->assertNotNull($parent);
        $this->assertNull($parent->sub_outcome);

        foreach ($rows as $row) {
            $this->assertEquals($parent->id, $row->fresh()->parent_id);
        }
    }

    public function test_backfill_leaves_single_row_outcomes_untouched(): void
    {
        $standalone = AgencyOutcome::create([
            'outcome' => 'Core Functions',
            'sub_outcome' => 'Core Functions',
            'function_type' => 'Core Functions',
        ]);

        $this->artisan('agency-outcomes:backfill-hierarchy')->assertSuccessful();

        $this->assertNull($standalone->fresh()->parent_id);
        $this->assertEquals(1, AgencyOutcome::count());
    }
}
