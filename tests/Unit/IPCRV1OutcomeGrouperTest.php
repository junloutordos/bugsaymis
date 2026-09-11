<?php

namespace Tests\Unit;

use App\Services\PerformanceManagement\IPCRV1OutcomeGrouper as Grouper;
use PHPUnit\Framework\TestCase;

/**
 * Asserts IPCRV1OutcomeGrouper (PHP) produces the same grouping structure as
 * resources/js/Utils/IPCR/outcomeGrouping.js on representative fixtures —
 * these test cases were hand-traced against the JS logic's actual behavior.
 */
class IPCRV1OutcomeGrouperTest extends TestCase
{
    private function plan(array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 1,
            'success_indicator' => 'Do the thing',
            'load_source' => null,
            'performance_indicator' => [
                'description' => 'PI Description',
                'agency_outcome' => [
                    'function_type' => 'Core Functions',
                    'outcome' => 'Outcome A',
                    'sub_outcome' => 'Sub A',
                    'parent' => null,
                ],
            ],
            'pivot' => [
                'individual_target' => null,
                'accomplishment' => null,
            ],
        ], $overrides);
    }

    public function test_normalize_function_type_matches_js_synonyms(): void
    {
        $this->assertSame('Strategic Functions', Grouper::normalizeFunctionType('strategic'));
        $this->assertSame('Strategic Functions', Grouper::normalizeFunctionType('Strategic Function'));
        $this->assertSame('Strategic Functions', Grouper::normalizeFunctionType('STRATEGIC FUNCTIONS'));
        $this->assertSame('Core Functions', Grouper::normalizeFunctionType('core'));
        $this->assertSame('Support Functions', Grouper::normalizeFunctionType('Support function'));
        $this->assertSame('Uncategorized', Grouper::normalizeFunctionType(null));
        $this->assertSame('Uncategorized', Grouper::normalizeFunctionType(''));
        // Unknown raw values pass through trimmed, unmodified — same as JS
        $this->assertSame('Custom Type', Grouper::normalizeFunctionType('  Custom Type  '));
    }

    public function test_sub_outcome_group_key_strips_tagged_plan_suffix(): void
    {
        $this->assertSame('—', Grouper::subOutcomeGroupKey(null));
        $this->assertSame('Teaching Load', Grouper::subOutcomeGroupKey('Teaching Load'));
        // "@<taggedPlanId>" suffix stripped so multiple tagged plans for the
        // same subject group merge into one Sub-Outcome cell
        $this->assertSame(
            'App\\Models\\FacultyLoading\\LoadAssignment#42',
            Grouper::subOutcomeGroupKey('App\\Models\\FacultyLoading\\LoadAssignment#42@7')
        );
    }

    public function test_sub_outcome_display_prefers_personalized_target_from_load_source_plan(): void
    {
        $pis = [
            'PI One' => [
                $this->plan(['load_source' => 'faculty_load', 'pivot' => ['individual_target' => 'My personalized target']]),
                $this->plan(),
            ],
        ];

        $this->assertSame('My personalized target', Grouper::subOutcomeDisplayFor($pis, 'Static Label'));
    }

    public function test_sub_outcome_display_falls_back_to_static_label_when_no_personalization(): void
    {
        $pis = [
            'PI One' => [$this->plan(), $this->plan()],
        ];

        $this->assertSame('Static Label', Grouper::subOutcomeDisplayFor($pis, 'Static Label'));
    }

    public function test_sub_outcome_display_uses_personalized_target_when_sub_outcome_marker_based(): void
    {
        $pis = [
            'PI One' => [
                $this->plan([
                    'performance_indicator' => ['agency_outcome' => ['sub_outcome' => 'App\\Models\\FacultyLoading\\LoadAssignment#42']],
                    'pivot' => ['individual_target' => 'Marker-based personalized target'],
                ]),
            ],
        ];

        $this->assertSame('Marker-based personalized target', Grouper::subOutcomeDisplayFor($pis, 'Static Label'));
    }

    public function test_sub_outcome_display_uses_personalized_target_when_no_static_sub_outcome_at_all(): void
    {
        $pis = [
            'PI One' => [
                $this->plan([
                    'performance_indicator' => ['agency_outcome' => ['sub_outcome' => null]],
                    'pivot' => ['individual_target' => 'Only personalized text exists'],
                ]),
            ],
        ];

        $this->assertSame('Only personalized text exists', Grouper::subOutcomeDisplayFor($pis, '—'));
    }

    public function test_group_plans_by_outcome_produces_four_level_nested_structure(): void
    {
        $plans = [
            $this->plan([
                'id' => 1,
                'performance_indicator' => [
                    'description' => 'PI Alpha',
                    'agency_outcome' => ['function_type' => 'Strategic', 'outcome' => 'Outcome Z', 'sub_outcome' => 'Sub 1'],
                ],
            ]),
            $this->plan([
                'id' => 2,
                'performance_indicator' => [
                    'description' => 'PI Beta',
                    'agency_outcome' => ['function_type' => 'core', 'outcome' => 'Outcome A', 'sub_outcome' => 'Sub 2'],
                ],
            ]),
            $this->plan([
                'id' => 3,
                'performance_indicator' => [
                    'description' => 'PI Beta',
                    'agency_outcome' => ['function_type' => 'core', 'outcome' => 'Outcome A', 'sub_outcome' => 'Sub 2'],
                ],
            ]),
        ];

        $grouped = Grouper::groupPlansByOutcome($plans);

        // Function types ordered per FUNCTION_TYPE_ORDER (Strategic before Core)
        $this->assertSame(['Strategic Functions', 'Core Functions'], array_keys($grouped));

        // 4-level nesting: FunctionType -> Outcome -> SubOutcome -> PIDesc -> [plans]
        $this->assertArrayHasKey('Outcome Z', $grouped['Strategic Functions']);
        $this->assertArrayHasKey('Sub 1', $grouped['Strategic Functions']['Outcome Z']);
        $this->assertArrayHasKey('PI Alpha', $grouped['Strategic Functions']['Outcome Z']['Sub 1']);
        $this->assertCount(1, $grouped['Strategic Functions']['Outcome Z']['Sub 1']['PI Alpha']);

        // Two plans sharing the same PI description merge into one group (2 plans, for rowspan)
        $this->assertCount(2, $grouped['Core Functions']['Outcome A']['Sub 2']['PI Beta']);
        $this->assertSame(2, $grouped['Core Functions']['Outcome A']['Sub 2']['PI Beta'][0]['id']);
        $this->assertSame(3, $grouped['Core Functions']['Outcome A']['Sub 2']['PI Beta'][1]['id']);
    }

    public function test_group_plans_by_outcome_sorts_unknown_function_types_alphabetically_after_known_ones(): void
    {
        $plans = [
            $this->plan(['performance_indicator' => ['agency_outcome' => ['function_type' => 'Zeta Custom']]]),
            $this->plan(['performance_indicator' => ['agency_outcome' => ['function_type' => 'Alpha Custom']]]),
            $this->plan(['performance_indicator' => ['agency_outcome' => ['function_type' => 'Support']]]),
        ];

        $grouped = Grouper::groupPlansByOutcome($plans);

        // Known types (in FUNCTION_TYPE_ORDER) always precede unknown ones,
        // and unknown ones are alphabetically sorted among themselves
        $this->assertSame(['Support Functions', 'Alpha Custom', 'Zeta Custom'], array_keys($grouped));
    }

    public function test_group_plans_by_outcome_defaults_missing_outcome_to_uncategorized(): void
    {
        $plans = [
            $this->plan(['performance_indicator' => ['agency_outcome' => ['outcome' => null, 'parent' => null]]]),
        ];

        $grouped = Grouper::groupPlansByOutcome($plans);
        $outcomeKeys = array_keys($grouped['Core Functions']);

        $this->assertSame(['Uncategorized'], $outcomeKeys);
    }

    public function test_group_plans_by_outcome_prefers_parent_outcome_over_own_outcome(): void
    {
        $plans = [
            $this->plan([
                'performance_indicator' => [
                    'agency_outcome' => [
                        'outcome' => 'Child Outcome',
                        'parent' => ['outcome' => 'Parent Outcome'],
                    ],
                ],
            ]),
        ];

        $grouped = Grouper::groupPlansByOutcome($plans);

        $this->assertArrayHasKey('Parent Outcome', $grouped['Core Functions']);
        $this->assertArrayNotHasKey('Child Outcome', $grouped['Core Functions']);
    }
}
