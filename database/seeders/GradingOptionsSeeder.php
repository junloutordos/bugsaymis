<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GradingOptionsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('grading_categories')->delete();
        DB::table('grading_options')->delete();

        $options = [
            [
                'name'        => 'Option 1: Project-based (SS/Science)',
                'description' => 'Used for Social Studies and Science subjects with project-based assessment.',
                'is_active'   => true,
                'categories'  => [
                    ['name' => 'Project 1',             'code' => 'P1', 'weight' => 0.2000, 'max_assessments' => 1, 'sort_order' => 1],
                    ['name' => 'Project 2',             'code' => 'P2', 'weight' => 0.2000, 'max_assessments' => 3, 'sort_order' => 2],
                    ['name' => 'Quarterly Exam',        'code' => 'QE', 'weight' => 0.3000, 'max_assessments' => 3, 'sort_order' => 3],
                    ['name' => 'Formative Assessments', 'code' => 'FA', 'weight' => 0.3000, 'max_assessments' => 4, 'sort_order' => 4],
                ],
            ],
            [
                'name'        => 'Option 2: Filipino Standard',
                'description' => 'Standard grading formula for Filipino language subjects.',
                'is_active'   => true,
                'categories'  => [
                    ['name' => 'Long/Summative Exam',      'code' => 'SE', 'weight' => 0.3000, 'max_assessments' => 1, 'sort_order' => 1],
                    ['name' => 'Alternative Assessments',  'code' => 'AA', 'weight' => 0.4000, 'max_assessments' => 3, 'sort_order' => 2],
                    ['name' => 'Formative Assessments',    'code' => 'FA', 'weight' => 0.3000, 'max_assessments' => 7, 'sort_order' => 3],
                ],
            ],
            [
                'name'        => 'Option 3: Science/Chemistry Standard',
                'description' => 'Standard grading formula for Science and Chemistry subjects.',
                'is_active'   => true,
                'categories'  => [
                    ['name' => 'Alternative Assessments',  'code' => 'AA', 'weight' => 0.7000, 'max_assessments' => 3, 'sort_order' => 1],
                    ['name' => 'Formative Assessments',    'code' => 'FA', 'weight' => 0.3000, 'max_assessments' => 4, 'sort_order' => 2],
                ],
            ],
            [
                'name'        => 'Option 4: Filipino Variant',
                'description' => 'Variant grading formula for Filipino language subjects with periodical exam.',
                'is_active'   => true,
                'categories'  => [
                    ['name' => 'Periodical Exam',          'code' => 'PE', 'weight' => 0.2500, 'max_assessments' => 1, 'sort_order' => 1],
                    ['name' => 'Quizzes',                  'code' => 'QZ', 'weight' => 0.1500, 'max_assessments' => 3, 'sort_order' => 2],
                    ['name' => 'Alternative Assessments',  'code' => 'AA', 'weight' => 0.3000, 'max_assessments' => 3, 'sort_order' => 3],
                    ['name' => 'Formative Assessments',    'code' => 'FA', 'weight' => 0.3000, 'max_assessments' => 4, 'sort_order' => 4],
                ],
            ],
        ];

        foreach ($options as $option) {
            $categories = $option['categories'];
            unset($option['categories']);

            $optionId = DB::table('grading_options')->insertGetId(array_merge($option, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            foreach ($categories as $category) {
                DB::table('grading_categories')->insert(array_merge($category, [
                    'grading_option_id' => $optionId,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]));
            }
        }

        $this->command->info('GradingOptionsSeeder: seeded 4 options with ' .
            DB::table('grading_categories')->count() . ' categories.');
    }
}
