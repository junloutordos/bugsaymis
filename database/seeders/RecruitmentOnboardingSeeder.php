<?php

namespace Database\Seeders;

use App\Models\OnboardingRequirement;
use App\Models\RecruitmentType;
use Illuminate\Database\Seeder;

class RecruitmentOnboardingSeeder extends Seeder
{
    private array $requirementsMap = [
        'Plantilla Teaching' => [
            ['requirement_name' => 'Appointment Paper (Original)',          'is_required' => true,  'sort_order' => 1],
            ['requirement_name' => 'Oath of Office',                        'is_required' => true,  'sort_order' => 2],
            ['requirement_name' => 'PDS (CS Form 212)',                     'is_required' => true,  'sort_order' => 3],
            ['requirement_name' => 'PRC License (Original + Photocopy)',    'is_required' => true,  'sort_order' => 4],
            ['requirement_name' => 'CSC Eligibility Certificate',           'is_required' => true,  'sort_order' => 5],
            ['requirement_name' => 'Transcript of Records',                 'is_required' => true,  'sort_order' => 6],
            ['requirement_name' => 'Diploma (Authenticated)',               'is_required' => true,  'sort_order' => 7],
            ['requirement_name' => 'NBI Clearance',                         'is_required' => true,  'sort_order' => 8],
            ['requirement_name' => 'Medical Certificate (PHIC-accredited)', 'is_required' => true,  'sort_order' => 9],
            ['requirement_name' => 'SALN',                                  'is_required' => true,  'sort_order' => 10],
            ['requirement_name' => 'BIR TIN (Form 1902)',                   'is_required' => true,  'sort_order' => 11],
            ['requirement_name' => 'GSIS Membership Form',                  'is_required' => true,  'sort_order' => 12],
            ['requirement_name' => 'PhilHealth Membership Form',            'is_required' => true,  'sort_order' => 13],
            ['requirement_name' => 'Pag-IBIG Membership Form',              'is_required' => true,  'sort_order' => 14],
            ['requirement_name' => '2x2 ID Photos (6 pcs)',                 'is_required' => true,  'sort_order' => 15],
        ],
        'Plantilla Non-Teaching' => [
            ['requirement_name' => 'Appointment Paper (Original)',          'is_required' => true,  'sort_order' => 1],
            ['requirement_name' => 'Oath of Office',                        'is_required' => true,  'sort_order' => 2],
            ['requirement_name' => 'PDS (CS Form 212)',                     'is_required' => true,  'sort_order' => 3],
            ['requirement_name' => 'CSC Eligibility Certificate',           'is_required' => true,  'sort_order' => 4],
            ['requirement_name' => 'Transcript of Records',                 'is_required' => true,  'sort_order' => 5],
            ['requirement_name' => 'Diploma (Authenticated)',               'is_required' => true,  'sort_order' => 6],
            ['requirement_name' => 'NBI Clearance',                         'is_required' => true,  'sort_order' => 7],
            ['requirement_name' => 'Medical Certificate',                   'is_required' => true,  'sort_order' => 8],
            ['requirement_name' => 'SALN',                                  'is_required' => true,  'sort_order' => 9],
            ['requirement_name' => 'BIR TIN',                               'is_required' => true,  'sort_order' => 10],
            ['requirement_name' => 'GSIS Membership Form',                  'is_required' => true,  'sort_order' => 11],
            ['requirement_name' => 'PhilHealth Membership Form',            'is_required' => true,  'sort_order' => 12],
            ['requirement_name' => 'Pag-IBIG Membership Form',              'is_required' => true,  'sort_order' => 13],
        ],
        'Cost of Service (COS) Teacher' => [
            ['requirement_name' => 'Contract of Service (signed)',          'is_required' => true,  'sort_order' => 1],
            ['requirement_name' => 'PRC License (Photocopy)',               'is_required' => true,  'sort_order' => 2],
            ['requirement_name' => 'Transcript of Records',                 'is_required' => true,  'sort_order' => 3],
            ['requirement_name' => 'Diploma',                               'is_required' => true,  'sort_order' => 4],
            ['requirement_name' => 'NBI Clearance',                         'is_required' => true,  'sort_order' => 5],
            ['requirement_name' => 'BIR TIN',                               'is_required' => true,  'sort_order' => 6],
            ['requirement_name' => 'PhilHealth Membership Form',            'is_required' => true,  'sort_order' => 7],
            ['requirement_name' => 'Pag-IBIG Membership Form',              'is_required' => true,  'sort_order' => 8],
            ['requirement_name' => '2x2 ID Photos',                         'is_required' => false, 'sort_order' => 9],
        ],
        'Job Order (JO)' => [
            ['requirement_name' => 'Job Order Contract (signed)',           'is_required' => true,  'sort_order' => 1],
            ['requirement_name' => 'BIR TIN',                               'is_required' => true,  'sort_order' => 2],
            ['requirement_name' => 'PhilHealth Membership',                 'is_required' => false, 'sort_order' => 3],
            ['requirement_name' => 'Pag-IBIG Membership',                   'is_required' => false, 'sort_order' => 4],
            ['requirement_name' => 'Barangay Clearance',                    'is_required' => true,  'sort_order' => 5],
            ['requirement_name' => 'Resume / Application Letter',           'is_required' => true,  'sort_order' => 6],
        ],
        'Outsourced Clerk' => [
            ['requirement_name' => 'Agency Contract / MOA',                 'is_required' => true,  'sort_order' => 1],
            ['requirement_name' => 'Agency-issued ID',                      'is_required' => true,  'sort_order' => 2],
            ['requirement_name' => 'NBI/Police Clearance',                  'is_required' => true,  'sort_order' => 3],
        ],
        'Government Internship Program (GIP)' => [
            ['requirement_name' => 'MOA / Endorsement Letter from CHED',    'is_required' => true,  'sort_order' => 1],
            ['requirement_name' => 'School Endorsement / Waiver',           'is_required' => true,  'sort_order' => 2],
            ['requirement_name' => 'Certificate of Enrollment',             'is_required' => true,  'sort_order' => 3],
            ['requirement_name' => 'Medical Certificate',                   'is_required' => false, 'sort_order' => 4],
            ['requirement_name' => 'Insurance / Accident Coverage',         'is_required' => false, 'sort_order' => 5],
        ],
        'On-the-Job Trainee (OJT)' => [
            ['requirement_name' => 'School Endorsement Letter',             'is_required' => true,  'sort_order' => 1],
            ['requirement_name' => 'Training Objectives / Competencies',    'is_required' => true,  'sort_order' => 2],
            ['requirement_name' => 'Parental Consent (if minor)',           'is_required' => false, 'sort_order' => 3],
            ['requirement_name' => 'Medical / Fitness Certificate',         'is_required' => false, 'sort_order' => 4],
            ['requirement_name' => 'Personal Accident Insurance',           'is_required' => false, 'sort_order' => 5],
        ],
    ];

    public function run(): void
    {
        foreach ($this->requirementsMap as $typeName => $requirements) {
            $type = RecruitmentType::where('name', $typeName)->first();
            if (! $type) continue;

            foreach ($requirements as $req) {
                OnboardingRequirement::firstOrCreate(
                    [
                        'recruitment_type_id' => $type->id,
                        'requirement_name'    => $req['requirement_name'],
                    ],
                    [
                        'is_required' => $req['is_required'],
                        'sort_order'  => $req['sort_order'],
                        'is_active'   => true,
                    ]
                );
            }
        }
    }
}
