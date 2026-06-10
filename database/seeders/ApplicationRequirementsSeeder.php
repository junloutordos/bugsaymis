<?php

namespace Database\Seeders;

use App\Models\ApplicationRequirement;
use Illuminate\Database\Seeder;

class ApplicationRequirementsSeeder extends Seeder
{
    public function run(): void
    {
        $requirements = [
            [
                'name'             => 'Application Letter',
                'description'      => 'Addressed to the Campus Director / Appointing Authority',
                'accepted_formats' => 'PDF, DOC, DOCX',
                'sort_order'       => 1,
            ],
            [
                'name'             => 'Personal Data Sheet (PDS)',
                'description'      => 'CSC Form 212 (Revised 2025 or latest edition), duly accomplished and signed',
                'accepted_formats' => 'PDF, DOC, DOCX',
                'sort_order'       => 2,
            ],
            [
                'name'             => 'Work Experience Sheet',
                'description'      => 'Attach the CSC Work Experience Sheet with signature',
                'accepted_formats' => 'PDF, DOC, DOCX',
                'sort_order'       => 3,
            ],
            [
                'name'             => 'Transcript of Records (TOR)',
                'description'      => 'Official Transcript of Records from the school/university',
                'accepted_formats' => 'PDF, JPG, PNG',
                'sort_order'       => 4,
            ],
            [
                'name'             => 'Diploma / Certificate of Graduation',
                'description'      => 'Authenticated copy of Diploma',
                'accepted_formats' => 'PDF, JPG, PNG',
                'sort_order'       => 5,
            ],
            [
                'name'             => 'Certificate of Eligibility',
                'description'      => 'CSC Certificate of Eligibility or equivalent civil service eligibility',
                'accepted_formats' => 'PDF, JPG, PNG',
                'sort_order'       => 6,
            ],
            [
                'name'             => 'PRC License / Professional ID',
                'description'      => 'PRC License Card / Professional Identification Card',
                'accepted_formats' => 'PDF, JPG, PNG',
                'sort_order'       => 7,
            ],
            [
                'name'             => 'IPCR / Performance Rating',
                'description'      => 'Latest IPCR with a rating of at least Very Satisfactory (if applicable)',
                'accepted_formats' => 'PDF, DOC, DOCX',
                'sort_order'       => 8,
            ],
            [
                'name'             => 'Certificate of Training',
                'description'      => 'Certificates of relevant trainings/seminars attended',
                'accepted_formats' => 'PDF, JPG, PNG',
                'sort_order'       => 9,
            ],
            [
                'name'             => 'NBI Clearance',
                'description'      => 'National Bureau of Investigation Clearance (within 6 months validity)',
                'accepted_formats' => 'PDF, JPG, PNG',
                'sort_order'       => 10,
            ],
            [
                'name'             => 'Medical Certificate',
                'description'      => 'Certificate of Physical Fitness from a government physician',
                'accepted_formats' => 'PDF, JPG, PNG',
                'sort_order'       => 11,
            ],
            [
                'name'             => 'Birth Certificate (PSA)',
                'description'      => 'PSA-authenticated Birth Certificate',
                'accepted_formats' => 'PDF, JPG, PNG',
                'sort_order'       => 12,
            ],
            [
                'name'             => 'Marriage Certificate (PSA)',
                'description'      => 'PSA-authenticated Marriage Certificate (if applicable)',
                'accepted_formats' => 'PDF, JPG, PNG',
                'sort_order'       => 13,
            ],
            [
                'name'             => 'TIN',
                'description'      => 'Tax Identification Number card or document',
                'accepted_formats' => 'PDF, JPG, PNG',
                'sort_order'       => 14,
            ],
            [
                'name'             => 'GSIS / SSS Number',
                'description'      => 'GSIS or SSS membership number / UMID card',
                'accepted_formats' => 'PDF, JPG, PNG',
                'sort_order'       => 15,
            ],
            [
                'name'             => 'PhilHealth Number',
                'description'      => 'PhilHealth MDR or ID',
                'accepted_formats' => 'PDF, JPG, PNG',
                'sort_order'       => 16,
            ],
            [
                'name'             => 'Pag-IBIG Number',
                'description'      => 'Pag-IBIG (HDMF) MID card or document',
                'accepted_formats' => 'PDF, JPG, PNG',
                'sort_order'       => 17,
            ],
            [
                'name'             => 'OJT / Internship Completion Certificate',
                'description'      => 'Certificate of Completion from the sponsoring agency (for OJT/GIP)',
                'accepted_formats' => 'PDF, DOC, DOCX',
                'sort_order'       => 18,
            ],
            [
                'name'             => 'MOA / Contract (for COS/JO)',
                'description'      => 'Memorandum of Agreement or Service Contract for Contract of Service / Job Order',
                'accepted_formats' => 'PDF, DOC, DOCX',
                'sort_order'       => 19,
            ],
        ];

        foreach ($requirements as $data) {
            ApplicationRequirement::updateOrCreate(
                ['name' => $data['name']],
                array_merge($data, ['is_active' => true])
            );
        }

        $this->command->info('Application requirements seeded: ' . count($requirements) . ' records.');
    }
}
