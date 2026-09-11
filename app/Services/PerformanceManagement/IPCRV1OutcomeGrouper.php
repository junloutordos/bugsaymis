<?php

namespace App\Services\PerformanceManagement;

use Illuminate\Support\Collection;

/**
 * PHP port of resources/js/Utils/IPCR/outcomeGrouping.js — kept 1:1 with the
 * JS logic so the server-rendered PDF groups plans exactly the way the
 * on-screen Show pages (Employee/DivisionChief/PMT/HR/Admin) already do.
 *
 * Any behavioral change here should be mirrored in outcomeGrouping.js (and
 * vice versa) to keep the screen and the PDF in sync.
 */
class IPCRV1OutcomeGrouper
{
    public const FUNCTION_TYPE_ORDER = [
        'Strategic Functions' => 1,
        'Core Functions'      => 2,
        'Support Functions'   => 3,
        'Uncategorized'       => 4,
    ];

    // Marker formats written by WorkDistributionPlanClassifier for an
    // auto-generated row's identity — a plain per-assignment Core/Support
    // fallback ("<SourceClass>#<assignmentId>") or a materialized Teaching
    // Load row ("<SourceClass>#<assignmentId>@<taggedPlanId>"). Both are
    // technical identifiers, never meant to be shown to a user.
    private const AUTO_GENERATED_MARKER = '/#\d+(@\d+)?$/';

    public static function normalizeFunctionType(?string $raw): string
    {
        if (! $raw) {
            return 'Uncategorized';
        }

        $t = mb_strtolower(trim($raw));

        if (in_array($t, ['strategic', 'strategic functions', 'strategic function'], true)) {
            return 'Strategic Functions';
        }
        if (in_array($t, ['core', 'core functions', 'core function'], true)) {
            return 'Core Functions';
        }
        if (in_array($t, ['support', 'support functions', 'support function'], true)) {
            return 'Support Functions';
        }

        return trim($raw);
    }

    /**
     * Grouping key for the Sub-Outcome column — strips the "@<taggedPlanId>"
     * suffix so multiple tagged plans for the same subject group still merge
     * into one Sub-Outcome cell instead of rendering as separate blocks.
     */
    public static function subOutcomeGroupKey(?string $raw): string
    {
        if (! $raw) {
            return '—';
        }

        return preg_replace('/@\d+$/', '', $raw);
    }

    /**
     * Display text for a merged Sub-Outcome cell. A personalized
     * individual_target (from a load_source-tagged plan, a marker-based
     * auto-generated row, or a plan with no static sub_outcome identity of
     * its own) overrides the static group label; otherwise the static label
     * is used as-is.
     *
     * @param array<string, array> $pis Performance-indicator groups within this sub-outcome (piDesc => [plans])
     */
    public static function subOutcomeDisplayFor(array $pis, string $staticLabel): string
    {
        $allPlans = [];
        foreach ($pis as $plans) {
            foreach ($plans as $plan) {
                $allPlans[] = $plan;
            }
        }

        foreach ($allPlans as $plan) {
            $individualTarget = $plan['pivot']['individual_target'] ?? null;
            if (! $individualTarget) {
                continue;
            }

            if (! empty($plan['load_source'])) {
                return $individualTarget;
            }

            $subOutcome = $plan['performance_indicator']['agency_outcome']['sub_outcome'] ?? null;
            if (! $subOutcome) {
                // no static identity marker to fall back to — must use the personalized text
                return $individualTarget;
            }

            if (preg_match(self::AUTO_GENERATED_MARKER, $subOutcome)) {
                return $individualTarget;
            }
        }

        return $staticLabel;
    }

    /**
     * Groups a flat list of plans into the 4-level nested structure the
     * Show pages / PDF render: Function Type -> Outcome -> SubOutcome ->
     * Performance Indicator description -> [plans].
     *
     * @param  Collection|array $plans Each plan as an array (or array-accessible)
     *                                  with keys: performance_indicator.agency_outcome.{function_type,parent.outcome,outcome,sub_outcome},
     *                                  performance_indicator.description, pivot.individual_target, load_source
     * @return array<string, array<string, array<string, array<string, array>>>>
     */
    public static function groupPlansByOutcome($plans): array
    {
        $groups = [];

        foreach ($plans as $plan) {
            $plan = self::toArray($plan);

            $aoo = $plan['performance_indicator']['agency_outcome'] ?? null;
            $functionType = self::normalizeFunctionType($aoo['function_type'] ?? null);
            $outcome = $aoo['parent']['outcome'] ?? ($aoo['outcome'] ?? 'Uncategorized');
            $subOutcome = self::subOutcomeGroupKey($aoo['sub_outcome'] ?? null);
            $piDesc = $plan['performance_indicator']['description'] ?? '—';

            $groups[$functionType] ??= [];
            $groups[$functionType][$outcome] ??= [];
            $groups[$functionType][$outcome][$subOutcome] ??= [];
            $groups[$functionType][$outcome][$subOutcome][$piDesc] ??= [];
            $groups[$functionType][$outcome][$subOutcome][$piDesc][] = $plan;
        }

        // Order function types: known order first, then any unknown types alphabetically
        $sorted = [];
        foreach (array_keys(self::FUNCTION_TYPE_ORDER) as $ft) {
            if (isset($groups[$ft])) {
                $sorted[$ft] = $groups[$ft];
            }
        }
        $unknownTypes = array_diff(array_keys($groups), array_keys(self::FUNCTION_TYPE_ORDER));
        sort($unknownTypes);
        foreach ($unknownTypes as $ft) {
            $sorted[$ft] = $groups[$ft];
        }

        // Sort outcomes / sub-outcomes / performance indicators alphabetically within each level
        foreach ($sorted as $ft => $outcomes) {
            $sortedOutcomes = [];
            $outcomeKeys = array_keys($outcomes);
            sort($outcomeKeys);

            foreach ($outcomeKeys as $outcome) {
                $subOutcomes = $outcomes[$outcome];
                $sortedSubOutcomes = [];
                $subKeys = array_keys($subOutcomes);
                sort($subKeys);

                foreach ($subKeys as $sub) {
                    $pis = $subOutcomes[$sub];
                    $sortedPis = [];
                    $piKeys = array_keys($pis);
                    sort($piKeys);

                    foreach ($piKeys as $piDesc) {
                        $sortedPis[$piDesc] = $pis[$piDesc];
                    }

                    $sortedSubOutcomes[$sub] = $sortedPis;
                }

                $sortedOutcomes[$outcome] = $sortedSubOutcomes;
            }

            $sorted[$ft] = $sortedOutcomes;
        }

        return $sorted;
    }

    /**
     * Normalizes a plan (Eloquent model, Collection-backed, or plain array)
     * into a plain nested array so the grouping logic can use uniform array
     * access regardless of caller shape.
     */
    private static function toArray($plan): array
    {
        if (is_array($plan)) {
            return $plan;
        }

        if ($plan instanceof \Illuminate\Contracts\Support\Arrayable) {
            return $plan->toArray();
        }

        return (array) $plan;
    }
}
