<?php

namespace App\Services\PPMP;

use App\Models\PPMP\Ppmp;
use App\Models\PPMP\PpmpItem;
use Illuminate\Support\Collection;

class ComplianceValidationService
{
    public function __construct(private ThresholdConfigService $thresholds)
    {
    }

    /**
     * Run all validations on a PPMP and its items.
     *
     * @return array{valid: bool, errors: array, warnings: array}
     */
    public function validatePPMP(Ppmp $ppmp, Collection $items): array
    {
        $errors   = [];
        $warnings = [];

        array_push($errors, ...$this->validatePPMPLevel($ppmp, $items));

        foreach ($items as $item) {
            array_push($errors,   ...$this->validateItem($item));

            $thresholdResult = $this->validateThresholds($item);
            array_push($errors,   ...$thresholdResult['errors']);
            array_push($warnings, ...$thresholdResult['warnings']);
        }

        array_push($errors, ...$this->validateDuplicates($items));

        return [
            'valid'    => empty($errors),
            'errors'   => $errors,
            'warnings' => $warnings,
        ];
    }

    private function validatePPMPLevel(Ppmp $ppmp, Collection $items): array
    {
        $errors = [];

        if ($items->isEmpty()) {
            $errors[] = [
                'item_id' => null,
                'field'   => null,
                'rule'    => 'min_items',
                'message' => 'PPMP must contain at least one procurement item.',
            ];
        }

        if ($ppmp->fiscal_year && $ppmp->fiscal_year < (int) date('Y')) {
            $errors[] = [
                'item_id' => null,
                'field'   => 'fiscal_year',
                'rule'    => 'future_year',
                'message' => 'Cannot create PPMP for a past fiscal year.',
            ];
        }

        return $errors;
    }

    private function validateItem(PpmpItem $item): array
    {
        $errors = [];

        if (empty(trim($item->description ?? ''))) {
            $errors[] = $this->itemError($item, 'description', 'required', 'Item description is required.');
        }

        if (empty(trim($item->unit ?? ''))) {
            $errors[] = $this->itemError($item, 'unit', 'required', 'Unit of measure is required.');
        }

        if (!in_array($item->category, PpmpItem::CATEGORIES)) {
            $errors[] = $this->itemError($item, 'category', 'required', 'Item category is required.');
        }

        if ($item->unit_cost <= 0) {
            $errors[] = $this->itemError($item, 'unit_cost', 'positive_cost', 'Unit cost must be greater than zero.');
        }

        $hasAnyMonth = collect(PpmpItem::MONTHS)->contains(fn ($m) => (int) $item->$m > 0);
        if (!$hasAnyMonth) {
            $errors[] = $this->itemError($item, 'schedule', 'min_month', 'At least one month must have a quantity greater than zero.');
        }

        if (empty($item->procurement_method) || !array_key_exists($item->procurement_method, PpmpItem::PROCUREMENT_METHODS)) {
            $errors[] = $this->itemError($item, 'procurement_method', 'required', 'Mode of procurement is required.');
        }

        // Part I integrity: catalogue items must use agency_to_agency
        if ($item->catalogue_id && $item->procurement_method !== 'agency_to_agency') {
            $errors[] = $this->itemError($item, 'procurement_method', 'part1_method', 'PS-DBM (Part I) items must use Agency-to-Agency procurement.');
        }

        // Fund source is mandatory — blocks submission (COA requirement)
        if (empty($item->fund_source) || !array_key_exists($item->fund_source, PpmpItem::FUND_SOURCES)) {
            $errors[] = $this->itemError($item, 'fund_source', 'fund_source_required', "Fund source is required for '{$item->description}' (MOOE/CO/PS/Trust Fund/SEF).");
        }

        // Consistency check (should always pass due to model saving event)
        $expectedQty  = collect(PpmpItem::MONTHS)->sum(fn ($m) => (int) $item->$m);
        $expectedCost = $expectedQty * $item->unit_cost;
        if (abs($item->total_cost - $expectedCost) > 0.01) {
            $errors[] = $this->itemError($item, 'total_cost', 'consistency', 'Computed total cost does not match.');
        }

        return $errors;
    }

    private function validateThresholds(PpmpItem $item): array
    {
        $errors   = [];
        $warnings = [];

        if ($item->procurement_method === 'shopping') {
            $threshold = $this->thresholds->getThreshold('shopping', $item->category);
            if ($threshold !== null && $item->total_cost > $threshold) {
                $formatted = number_format($threshold, 0);
                $label     = PpmpItem::CATEGORY_LABELS[$item->category] ?? $item->category;
                $warnings[] = $this->itemWarning(
                    $item,
                    'procurement_method',
                    'shopping_threshold',
                    "Shopping threshold exceeded for {$label} (₱{$formatted}). Consider Competitive Bidding."
                );
            }
        }

        if ($item->procurement_method === 'svp') {
            $threshold = $this->thresholds->getThreshold('svp', $item->category);
            if ($threshold !== null && $item->total_cost > $threshold) {
                $formatted = number_format($threshold, 0);
                $label     = PpmpItem::CATEGORY_LABELS[$item->category] ?? $item->category;
                $warnings[] = $this->itemWarning(
                    $item,
                    'procurement_method',
                    'svp_threshold',
                    "SVP threshold exceeded for {$label} (₱{$formatted}). Consider Competitive Bidding."
                );
            }
        }

        if (in_array($item->procurement_method, ['direct_contracting', 'emergency', 'lease_real_property', 'two_failed_biddings'])) {
            if (empty(trim($item->remarks ?? ''))) {
                $label = PpmpItem::PROCUREMENT_METHODS[$item->procurement_method] ?? $item->procurement_method;
                $errors[] = $this->itemError($item, 'remarks', 'justification_required', "Justification/remarks required for {$label}.");
            }
        }

        if ($item->procurement_method === 'repeat_order') {
            $warnings[] = $this->itemWarning(
                $item,
                'procurement_method',
                'repeat_order_reminder',
                'Repeat Order should not exceed 25% of the original contract value (RA 9184 Sec. 51).'
            );
        }

        // Warn if agency_to_agency is used without a catalogue link (possible legacy/manual entry)
        if ($item->procurement_method === 'agency_to_agency' && !$item->catalogue_id) {
            $warnings[] = $this->itemWarning(
                $item,
                'procurement_method',
                'a2a_without_catalogue',
                "Agency-to-Agency item '{$item->description}' is not linked to the PS-DBM catalogue. Verify it is a CUSE item."
            );
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    private function validateDuplicates(Collection $items): array
    {
        $errors = [];
        $seen   = [];

        foreach ($items as $item) {
            $key = strtolower(trim($item->description)) . '|' . strtolower(trim($item->unit)) . '|' . $item->category;
            if (isset($seen[$key])) {
                $errors[] = [
                    'item_id' => $item->id,
                    'field'   => 'description',
                    'rule'    => 'duplicate_item',
                    'message' => "Duplicate item detected: {$item->description}.",
                ];
            }
            $seen[$key] = true;
        }

        return $errors;
    }

    private function itemError(PpmpItem $item, string $field, string $rule, string $message): array
    {
        return ['item_id' => $item->id, 'field' => $field, 'rule' => $rule, 'message' => $message];
    }

    private function itemWarning(PpmpItem $item, string $field, string $rule, string $message): array
    {
        return ['item_id' => $item->id, 'field' => $field, 'rule' => $rule, 'message' => $message];
    }
}
