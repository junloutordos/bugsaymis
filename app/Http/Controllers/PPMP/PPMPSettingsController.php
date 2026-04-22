<?php

namespace App\Http\Controllers\PPMP;

use App\Http\Controllers\Controller;
use App\Models\PPMP\Ppmp;
use App\Models\PPMP\PpmpSetting;
use App\Services\PPMP\ThresholdConfigService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PPMPSettingsController extends Controller
{
    public function index(ThresholdConfigService $thresholdService)
    {
        $this->authorize('consolidate', Ppmp::class);

        return Inertia::render('PPMP/Settings', [
            'settings' => [
                'agency_name'         => PpmpSetting::getValue('agency_name', 'Philippine Science High School - Caraga Region Campus'),
                'active_fiscal_year'  => (int) PpmpSetting::getValue('active_fiscal_year', date('Y')),
                'submission_deadline' => PpmpSetting::getValue('submission_deadline_' . date('Y')),
            ],
            'thresholds' => $thresholdService->getAllThresholds(),
        ]);
    }

    public function update(Request $request, ThresholdConfigService $thresholdService)
    {
        $this->authorize('consolidate', Ppmp::class);

        $data = $request->validate([
            'agency_name'                      => 'nullable|string|max:255',
            'active_fiscal_year'               => 'nullable|integer|min:2020',
            'submission_deadline'              => 'nullable|date',
            'thresholds'                       => 'nullable|array',
            'thresholds.shopping_goods'        => 'nullable|numeric|min:0',
            'thresholds.shopping_infrastructure' => 'nullable|numeric|min:0',
        ]);

        if (isset($data['agency_name'])) {
            PpmpSetting::setValue('agency_name', $data['agency_name']);
        }

        if (isset($data['active_fiscal_year'])) {
            PpmpSetting::setValue('active_fiscal_year', $data['active_fiscal_year']);
        }

        if (isset($data['submission_deadline'])) {
            $fy = $data['active_fiscal_year'] ?? date('Y');
            PpmpSetting::setValue("submission_deadline_{$fy}", $data['submission_deadline']);
        }

        if (!empty($data['thresholds'])) {
            foreach ($data['thresholds'] as $key => $value) {
                // key format: "shopping_goods" → method=shopping, category=goods
                $parts = explode('_', $key, 2);
                if (count($parts) === 2) {
                    $thresholdService->updateThreshold($parts[0], $parts[1], $value);
                }
            }
        }

        return back()->with('success', 'Settings updated.');
    }
}
