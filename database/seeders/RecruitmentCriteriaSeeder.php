<?php

namespace Database\Seeders;

use App\Models\EvaluationCriteria;
use App\Models\RecruitmentType;
use Illuminate\Database\Seeder;

class RecruitmentCriteriaSeeder extends Seeder
{
    /**
     * Default weighted criteria per recruitment type.
     * Weights must sum to 100.00 per type.
     * Based on CSC MC No. 24, s. 2017 and DepEd/PSHS guidelines.
     */
    private array $criteriaMap = [
        'Plantilla Teaching' => [
            ['name' => 'Education',           'weight_percentage' => 20.00, 'scoring_guide' => 'Degree relevance and level (BS=10, Masters=15, Doctorate=20)'],
            ['name' => 'Training/Experience', 'weight_percentage' => 15.00, 'scoring_guide' => 'Years of relevant teaching experience'],
            ['name' => 'Eligibility',         'weight_percentage' => 15.00, 'scoring_guide' => 'LET/PRC license rating; CSC Professional eligibility'],
            ['name' => 'Written Exam',        'weight_percentage' => 20.00, 'scoring_guide' => 'Qualifying written examination score (0-100)'],
            ['name' => 'Demo Teaching',       'weight_percentage' => 20.00, 'scoring_guide' => 'Rated by panel using NCBSSH rubric (0-100)'],
            ['name' => 'Interview',           'weight_percentage' => 10.00, 'scoring_guide' => 'Panel interview rating (0-100)'],
        ],
        'Plantilla Non-Teaching' => [
            ['name' => 'Education',           'weight_percentage' => 25.00, 'scoring_guide' => 'Degree relevance and level'],
            ['name' => 'Training/Experience', 'weight_percentage' => 25.00, 'scoring_guide' => 'Years of relevant work experience'],
            ['name' => 'Eligibility',         'weight_percentage' => 20.00, 'scoring_guide' => 'CSC eligibility rating'],
            ['name' => 'Written Exam',        'weight_percentage' => 15.00, 'scoring_guide' => 'Qualifying written examination score (0-100)'],
            ['name' => 'Interview',           'weight_percentage' => 15.00, 'scoring_guide' => 'Panel interview rating (0-100)'],
        ],
        'Cost of Service (COS) Teacher' => [
            ['name' => 'Education',           'weight_percentage' => 30.00, 'scoring_guide' => 'Degree and relevant units earned'],
            ['name' => 'Experience',          'weight_percentage' => 30.00, 'scoring_guide' => 'Teaching experience in subject area'],
            ['name' => 'PRC License',         'weight_percentage' => 20.00, 'scoring_guide' => 'LET rating or equivalent'],
            ['name' => 'Interview',           'weight_percentage' => 20.00, 'scoring_guide' => 'Panel interview rating (0-100)'],
        ],
        'Job Order (JO)' => [
            ['name' => 'Education',           'weight_percentage' => 40.00, 'scoring_guide' => 'Minimum required education for the position'],
            ['name' => 'Experience',          'weight_percentage' => 30.00, 'scoring_guide' => 'Relevant work experience'],
            ['name' => 'Interview',           'weight_percentage' => 30.00, 'scoring_guide' => 'Interview rating (0-100)'],
        ],
        'Government Internship Program (GIP)' => [
            ['name' => 'Academic Standing',   'weight_percentage' => 50.00, 'scoring_guide' => 'Current GWA or equivalent'],
            ['name' => 'Interview',           'weight_percentage' => 50.00, 'scoring_guide' => 'Interview rating (0-100)'],
        ],
    ];

    public function run(): void
    {
        foreach ($this->criteriaMap as $typeName => $criteria) {
            $type = RecruitmentType::where('name', $typeName)->first();
            if (! $type) continue;

            foreach ($criteria as $criterion) {
                EvaluationCriteria::firstOrCreate(
                    [
                        'recruitment_type_id' => $type->id,
                        'name'                => $criterion['name'],
                    ],
                    [
                        'weight_percentage' => $criterion['weight_percentage'],
                        'scoring_guide'     => $criterion['scoring_guide'],
                        'is_active'         => true,
                    ]
                );
            }
        }
    }
}
