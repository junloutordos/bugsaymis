<?php

namespace App\Services\PerformanceManagementV2;

use App\Models\PM2\EmployeeIpcrV2;
use App\Models\PM2\OpcrTemplate;

class StrategicFunctionInheritorV2
{
    public function inherit(EmployeeIpcrV2 $ipcr): int
    {
        $template = OpcrTemplate::where('ipcr_rating_period_v2_id', $ipcr->rating_period_id)
            ->where('is_current', true)
            ->with('items')
            ->first();

        if (! $template) {
            return 0;
        }

        $existingItemIds = $ipcr->rows()->whereNotNull('opcr_template_item_id')->pluck('opcr_template_item_id');
        $created = 0;

        foreach ($template->items as $item) {
            if ($existingItemIds->contains($item->id)) {
                continue;
            }

            $ipcr->rows()->create([
                'function_type'         => 'strategic',
                'opcr_template_item_id' => $item->id,
                'weight_percent'        => $item->weight_percent,
                'individual_target'     => $item->target,
            ]);
            $created++;
        }

        return $created;
    }
}
