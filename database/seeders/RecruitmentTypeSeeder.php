<?php

namespace Database\Seeders;

use App\Models\RecruitmentType;
use Illuminate\Database\Seeder;

class RecruitmentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name'                    => 'Plantilla Teaching',
                'description'             => 'Permanent teaching positions under DBM-approved plantilla items.',
                'has_ranking'             => true,
                'has_exam'                => true,
                'has_interview'           => true,
                'requires_csc_eligibility'=> true,
                'requires_prc_license'    => true,
            ],
            [
                'name'                    => 'Plantilla Non-Teaching',
                'description'             => 'Permanent non-teaching positions under DBM-approved plantilla items.',
                'has_ranking'             => true,
                'has_exam'                => true,
                'has_interview'           => true,
                'requires_csc_eligibility'=> true,
                'requires_prc_license'    => false,
            ],
            [
                'name'                    => 'Cost of Service (COS) Teacher',
                'description'             => 'Contract-of-Service teachers funded from school income or special funds.',
                'has_ranking'             => true,
                'has_exam'                => false,
                'has_interview'           => true,
                'requires_csc_eligibility'=> false,
                'requires_prc_license'    => true,
            ],
            [
                'name'                    => 'Job Order (JO)',
                'description'             => 'Job-order workers for specific outputs; no employer-employee relationship.',
                'has_ranking'             => false,
                'has_exam'                => false,
                'has_interview'           => true,
                'requires_csc_eligibility'=> false,
                'requires_prc_license'    => false,
            ],
            [
                'name'                    => 'Outsourced Clerk',
                'description'             => 'Administrative clerks sourced through a third-party agency.',
                'has_ranking'             => false,
                'has_exam'                => false,
                'has_interview'           => false,
                'requires_csc_eligibility'=> false,
                'requires_prc_license'    => false,
            ],
            [
                'name'                    => 'Government Internship Program (GIP)',
                'description'             => 'College students serving as interns under the CHED-GIP program.',
                'has_ranking'             => false,
                'has_exam'                => false,
                'has_interview'           => true,
                'requires_csc_eligibility'=> false,
                'requires_prc_license'    => false,
            ],
            [
                'name'                    => 'On-the-Job Trainee (OJT)',
                'description'             => 'Students undergoing on-the-job training as part of their academic curriculum.',
                'has_ranking'             => false,
                'has_exam'                => false,
                'has_interview'           => false,
                'requires_csc_eligibility'=> false,
                'requires_prc_license'    => false,
            ],
        ];

        foreach ($types as $type) {
            RecruitmentType::firstOrCreate(
                ['name' => $type['name']],
                array_merge($type, ['is_active' => true])
            );
        }
    }
}
