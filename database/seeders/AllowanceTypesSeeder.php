<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AllowanceTypesSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            // ── Allowances ──────────────────────────────────────────────────
            [
                'code'            => 'PERA',
                'name'            => 'Personnel Economic Relief Allowance',
                'description'     => 'P2,000/month for all government employees.',
                'category'        => 'allowance',
                'is_taxable'      => false,
                'is_mandatory'    => true,
                'is_fixed_amount' => true,
                'default_amount'  => 2000.0000,
                'sg_min'          => null,
                'sg_max'          => null,
                'sort_order'      => 1,
            ],
            [
                'code'            => 'RATA',
                'name'            => 'Representation and Transportation Allowance',
                'description'     => 'Monthly RATA for qualified positions.',
                'category'        => 'allowance',
                'is_taxable'      => false,
                'is_mandatory'    => false,
                'is_fixed_amount' => true,
                'default_amount'  => 0.0000,
                'sg_min'          => 24,
                'sg_max'          => null,
                'sort_order'      => 2,
            ],
            [
                'code'            => 'ACA',
                'name'            => 'Additional Compensation Allowance',
                'description'     => 'P2,000/month additional compensation.',
                'category'        => 'allowance',
                'is_taxable'      => false,
                'is_mandatory'    => true,
                'is_fixed_amount' => true,
                'default_amount'  => 2000.0000,
                'sg_min'          => null,
                'sg_max'          => null,
                'sort_order'      => 3,
            ],
            [
                'code'            => 'COLA',
                'name'            => 'Cost of Living Allowance',
                'description'     => 'Cost of living allowance when prescribed.',
                'category'        => 'allowance',
                'is_taxable'      => false,
                'is_mandatory'    => false,
                'is_fixed_amount' => true,
                'default_amount'  => 0.0000,
                'sg_min'          => null,
                'sg_max'          => null,
                'sort_order'      => 4,
            ],

            // ── Mandatory Deductions ─────────────────────────────────────────
            [
                'code'            => 'GSIS-LIFE',
                'name'            => 'GSIS Life Insurance Premium',
                'description'     => '2% of monthly salary (employee share).',
                'category'        => 'deduction',
                'is_taxable'      => false,
                'is_mandatory'    => true,
                'is_fixed_amount' => false,
                'default_amount'  => 0.0200,  // 2%
                'sg_min'          => null,
                'sg_max'          => null,
                'sort_order'      => 10,
            ],
            [
                'code'            => 'GSIS-RET',
                'name'            => 'GSIS Retirement Premium',
                'description'     => '9% of monthly salary (employee share).',
                'category'        => 'deduction',
                'is_taxable'      => false,
                'is_mandatory'    => true,
                'is_fixed_amount' => false,
                'default_amount'  => 0.0900,  // 9%
                'sg_min'          => null,
                'sg_max'          => null,
                'sort_order'      => 11,
            ],
            [
                'code'            => 'PAGIBIG',
                'name'            => 'Pag-IBIG Fund Contribution',
                'description'     => 'P100/month mandatory contribution.',
                'category'        => 'deduction',
                'is_taxable'      => false,
                'is_mandatory'    => true,
                'is_fixed_amount' => true,
                'default_amount'  => 100.0000,
                'sg_min'          => null,
                'sg_max'          => null,
                'sort_order'      => 12,
            ],
            [
                'code'            => 'PHILHEALTH',
                'name'            => 'PhilHealth Contribution',
                'description'     => '5% of monthly salary, capped per PhilHealth schedule.',
                'category'        => 'deduction',
                'is_taxable'      => false,
                'is_mandatory'    => true,
                'is_fixed_amount' => false,
                'default_amount'  => 0.0500,  // 5%
                'sg_min'          => null,
                'sg_max'          => null,
                'sort_order'      => 13,
            ],
            [
                'code'            => 'BIR-WHT',
                'name'            => 'Withholding Tax',
                'description'     => 'Monthly withholding tax per BIR tax table.',
                'category'        => 'deduction',
                'is_taxable'      => false,
                'is_mandatory'    => true,
                'is_fixed_amount' => false,
                'default_amount'  => 0.0000,  // computed per BIR table
                'sg_min'          => null,
                'sg_max'          => null,
                'sort_order'      => 14,
            ],

            // ── Voluntary Deductions ─────────────────────────────────────────
            [
                'code'            => 'GSIS-LOAN',
                'name'            => 'GSIS Loan',
                'description'     => 'GSIS consolidated loan amortization.',
                'category'        => 'deduction',
                'is_taxable'      => false,
                'is_mandatory'    => false,
                'is_fixed_amount' => true,
                'default_amount'  => 0.0000,
                'sg_min'          => null,
                'sg_max'          => null,
                'sort_order'      => 20,
            ],
            [
                'code'            => 'PAGIBIG-LOAN',
                'name'            => 'Pag-IBIG Multi-Purpose Loan',
                'description'     => 'Pag-IBIG MPL/calamity loan amortization.',
                'category'        => 'deduction',
                'is_taxable'      => false,
                'is_mandatory'    => false,
                'is_fixed_amount' => true,
                'default_amount'  => 0.0000,
                'sg_min'          => null,
                'sg_max'          => null,
                'sort_order'      => 21,
            ],

            // ── Year-end Bonus / Incentives ──────────────────────────────────
            [
                'code'            => 'YEB',
                'name'            => 'Year-End Bonus',
                'description'     => '1 month basic salary (DBM guidelines).',
                'category'        => 'bonus',
                'is_taxable'      => false,
                'is_mandatory'    => false,
                'is_fixed_amount' => false,
                'default_amount'  => 1.0000,  // 100% of monthly basic
                'sg_min'          => null,
                'sg_max'          => null,
                'sort_order'      => 30,
            ],
            [
                'code'            => '13TH',
                'name'            => '13th Month Pay',
                'description'     => 'P5,000 cash gift + 13th month.',
                'category'        => 'bonus',
                'is_taxable'      => false,
                'is_mandatory'    => false,
                'is_fixed_amount' => true,
                'default_amount'  => 5000.0000,
                'sg_min'          => null,
                'sg_max'          => null,
                'sort_order'      => 31,
            ],
        ];

        $all = array_map(fn ($t) => array_merge($t, [
            'applicable_employment_types' => '["permanent","casual","contractual","coterminous","substitute"]',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]), $types);

        foreach ($all as $t) {
            DB::table('allowance_types')->updateOrInsert(
                ['code' => $t['code']],
                $t
            );
        }
    }
}
