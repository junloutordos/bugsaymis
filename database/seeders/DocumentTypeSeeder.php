<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentType;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            // ── External incoming types ───────────────────────────────────────
            ['name' => 'Letter/Correspondence',          'code' => 'LTR',   'lead_time_hours' => 24,  'applicable_to' => 'external', 'description' => 'Letters and correspondence received from external agencies or offices.'],
            ['name' => 'Indorsement',                    'code' => 'IND',   'lead_time_hours' => 24,  'applicable_to' => 'external', 'description' => 'Documents endorsed or forwarded from an external office or agency.'],

            // ── Internal types ────────────────────────────────────────────────
            ['name' => 'Memorandum',                     'code' => 'MEMO',  'lead_time_hours' => 16,  'applicable_to' => 'internal', 'description' => 'Internal memorandum between offices or divisions.'],
            ['name' => 'Leave Application',              'code' => 'LEAVE', 'lead_time_hours' => 40,  'applicable_to' => 'internal', 'description' => 'Application for leave of absence (vacation, sick, etc.).'],
            ['name' => 'Request for Authority to Travel','code' => 'RAT',   'lead_time_hours' => 24,  'applicable_to' => 'internal', 'description' => 'Authority to travel on official business outside of station.'],
            ['name' => 'Certificate Request',            'code' => 'CERT',  'lead_time_hours' => 56,  'applicable_to' => 'internal', 'description' => 'Request for certificates (service, employment, no pending case, etc.).'],
            ['name' => 'Purchase Request',               'code' => 'PR',    'lead_time_hours' => 40,  'applicable_to' => 'internal', 'description' => 'Request for procurement of goods and services.'],
            ['name' => 'Travel Order',                   'code' => 'TO',    'lead_time_hours' => 24,  'applicable_to' => 'internal', 'description' => 'Official travel order issued by the OCD.'],
            ['name' => 'Administrative Report',          'code' => 'ARPT',  'lead_time_hours' => 80,  'applicable_to' => 'internal', 'description' => 'Administrative and accomplishment reports.'],
            ['name' => 'Special Order',                  'code' => 'SO',    'lead_time_hours' => 24,  'applicable_to' => 'internal', 'description' => 'Special orders and directives from OCD.'],
            ['name' => 'Voucher/Payment Request',        'code' => 'VCH',   'lead_time_hours' => 40,  'applicable_to' => 'internal', 'description' => 'Payment or disbursement vouchers for processing.'],
            ['name' => 'Contract/MOA/MOU',               'code' => 'CON',   'lead_time_hours' => 120, 'applicable_to' => 'internal', 'description' => 'Contracts, Memoranda of Agreement, and Memoranda of Understanding.'],
            ['name' => 'Request for Quotation',          'code' => 'RFQ',   'lead_time_hours' => 56,  'applicable_to' => 'internal', 'description' => 'Request for price quotations from suppliers.'],
            ['name' => 'Instruction/Directive',          'code' => 'DIR',   'lead_time_hours' => 16,  'applicable_to' => 'internal', 'description' => 'Instructions and directives from management.'],

            // ── Both ──────────────────────────────────────────────────────────
            ['name' => 'Others',                         'code' => 'OTH',   'lead_time_hours' => 40,  'applicable_to' => 'both',     'description' => 'Other document types not covered by the above categories.'],
        ];

        foreach ($types as $type) {
            DocumentType::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
