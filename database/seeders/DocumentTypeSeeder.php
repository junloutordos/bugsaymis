<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentType;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Letter/Correspondence',          'code' => 'LTR',   'lead_time_hours' => 24,  'description' => 'General correspondence and letters between offices or individuals.'],
            ['name' => 'Memorandum',                     'code' => 'MEMO',  'lead_time_hours' => 16,  'description' => 'Internal memorandum between offices or divisions.'],
            ['name' => 'Leave Application',              'code' => 'LEAVE', 'lead_time_hours' => 40,  'description' => 'Application for leave of absence (vacation, sick, etc.).'],
            ['name' => 'Request for Authority to Travel','code' => 'RAT',   'lead_time_hours' => 24,  'description' => 'Authority to travel on official business outside of station.'],
            ['name' => 'Certificate Request',            'code' => 'CERT',  'lead_time_hours' => 56,  'description' => 'Request for certificates (service, employment, no pending case, etc.).'],
            ['name' => 'Purchase Request',               'code' => 'PR',    'lead_time_hours' => 40,  'description' => 'Request for procurement of goods and services.'],
            ['name' => 'Travel Order',                   'code' => 'TO',    'lead_time_hours' => 24,  'description' => 'Official travel order issued by the OCD.'],
            ['name' => 'Administrative Report',          'code' => 'ARPT',  'lead_time_hours' => 80,  'description' => 'Administrative and accomplishment reports.'],
            ['name' => 'Special Order',                  'code' => 'SO',    'lead_time_hours' => 24,  'description' => 'Special orders and directives from OCD.'],
            ['name' => 'Indorsement',                    'code' => 'IND',   'lead_time_hours' => 24,  'description' => 'Endorsement or forwarding of documents to another office.'],
            ['name' => 'Voucher/Payment Request',        'code' => 'VCH',   'lead_time_hours' => 40,  'description' => 'Payment or disbursement vouchers for processing.'],
            ['name' => 'Contract/MOA/MOU',               'code' => 'CON',   'lead_time_hours' => 120, 'description' => 'Contracts, Memoranda of Agreement, and Memoranda of Understanding.'],
            ['name' => 'Request for Quotation',          'code' => 'RFQ',   'lead_time_hours' => 56,  'description' => 'Request for price quotations from suppliers.'],
            ['name' => 'Instruction/Directive',          'code' => 'DIR',   'lead_time_hours' => 16,  'description' => 'Instructions and directives from management.'],
            ['name' => 'Others',                         'code' => 'OTH',   'lead_time_hours' => 40,  'description' => 'Other document types not covered by the above categories.'],
        ];

        foreach ($types as $type) {
            DocumentType::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
