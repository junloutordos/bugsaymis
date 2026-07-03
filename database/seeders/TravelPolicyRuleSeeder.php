<?php

namespace Database\Seeders;

use App\Models\TravelPolicyRule;
use Illuminate\Database\Seeder;

class TravelPolicyRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            [
                'source' => 'EO 77, s. 2019',
                'rule_key' => 'official_travel_allowances',
                'label' => 'Official local and foreign travel expenses and allowances',
                'value' => [
                    'note' => 'Use current EO 77 rates and agency-specific issuances when computing per diem and reimbursable expenses.',
                ],
                'source_url' => 'https://www.officialgazette.gov.ph/downloads/2019/03mar/20190315-EO-77-RRD.pdf',
                'effective_date' => '2019-03-15',
            ],
            [
                'source' => 'RA 12009',
                'rule_key' => 'procurement_for_airfare',
                'label' => 'Government procurement requirements for airfare and travel-related services',
                'value' => [
                    'note' => 'Route airfare/booking requirements through the applicable procurement process and agency rules.',
                ],
                'source_url' => 'https://www.officialgazette.gov.ph/2024/07/20/republic-act-no-12009/',
                'effective_date' => '2024-07-20',
            ],
            [
                'source' => 'COA/DBM travel liquidation rules',
                'rule_key' => 'cash_advance_liquidation_checklist',
                'label' => 'Cash advance and liquidation supporting documents',
                'value' => [
                    'checklist' => [
                        'Approved Travel Order or equivalent authority',
                        'Approved Proposed Itinerary of Travel',
                        'Transportation tickets/boarding passes when applicable',
                        'Certificate of Appearance or equivalent proof of attendance',
                        'Official receipts or acceptable supporting documents',
                        'Liquidation report within the prescribed period',
                    ],
                ],
                'source_url' => null,
                'effective_date' => null,
            ],
        ];

        foreach ($rules as $rule) {
            TravelPolicyRule::updateOrCreate(
                [
                    'source' => $rule['source'],
                    'rule_key' => $rule['rule_key'],
                    'effective_date' => $rule['effective_date'],
                ],
                $rule + ['is_active' => true],
            );
        }
    }
}
