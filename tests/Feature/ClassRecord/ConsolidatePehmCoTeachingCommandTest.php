<?php

namespace Tests\Feature\ClassRecord;

use App\Console\Commands\ClassRecord\ConsolidatePehmCoTeaching;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidatePehmCoTeachingCommandTest extends TestCase
{
    use RefreshDatabase;

    private function pehmSchoolYear(): SchoolYear
    {
        return SchoolYear::firstOrCreate(
            ['name' => '2026-2027'],
            ['is_current' => true, 'start_date' => '2026-06-01', 'end_date' => '2027-03-31']
        );
    }

    /** @return array<int,array<string,int>> grade_level => ['Health'=>id,'Music'=>id,'PE'=>id] of the created subjects */
    private function makePehmSubjects(): array
    {
        $sy = $this->pehmSchoolYear();

        $make = fn (string $code, string $name, int $grade) => Subject::create([
            'code' => $code, 'name' => $name, 'subject_group' => 'PEHM', 'grade_level' => $grade,
            'subject_type' => 'lecture', 'school_year_id' => $sy->id,
        ])->id;

        return [
            7 => ['Health' => $make('HLTH1', 'Health 1', 7), 'Music' => $make('MUS1', 'Music 1', 7), 'PE' => $make('PE1', 'Physical Education 1', 7)],
            8 => ['Health' => $make('HLTH2', 'Health 2', 8), 'Music' => $make('MUS2', 'Music 2', 8), 'PE' => $make('PE2', 'Physical Education 2', 8)],
        ];
    }

    public function test_role_for_category_detects_pe_health_music_from_name_null_otherwise(): void
    {
        $option = GradingOption::create(['name' => 'PEHM 1-3']);
        $pe = GradingCategory::create(['grading_option_id' => $option->id, 'name' => 'SA (PE)', 'code' => 'SA 1', 'weight' => 0.10, 'max_assessments' => 1]);
        $health = GradingCategory::create(['grading_option_id' => $option->id, 'name' => 'SA (Health)', 'code' => 'SA 2', 'weight' => 0.10, 'max_assessments' => 1]);
        $music = GradingCategory::create(['grading_option_id' => $option->id, 'name' => 'SA (Music)', 'code' => 'SA 3', 'weight' => 0.10, 'max_assessments' => 1]);
        $parent = GradingCategory::create(['grading_option_id' => $option->id, 'name' => 'Summative Assessment', 'code' => 'SA', 'weight' => 0.30, 'max_assessments' => 1]);

        $cmd = new ConsolidatePehmCoTeaching();

        $this->assertSame('PE', $cmd->roleForCategory($pe));
        $this->assertSame('Health', $cmd->roleForCategory($health));
        $this->assertSame('Music', $cmd->roleForCategory($music));
        $this->assertNull($cmd->roleForCategory($parent));
    }

    public function test_strip_code_removes_trailing_number(): void
    {
        $cmd = new ConsolidatePehmCoTeaching();

        $this->assertSame('SA', $cmd->stripCode('SA 1'));
        $this->assertSame('FA', $cmd->stripCode('FA 2'));
        $this->assertSame('AA', $cmd->stripCode('AA 3'));
        $this->assertSame('SA', $cmd->stripCode('SA'));
    }

    public function test_subjects_by_grade_and_role_maps_grade_level_and_role_to_subject_id(): void
    {
        $expected = $this->makePehmSubjects();
        $cmd = new ConsolidatePehmCoTeaching();

        $map = $cmd->subjectsByGradeAndRole();

        $this->assertSame($expected[7], $map[7]);
        $this->assertSame($expected[8], $map[8]);
    }
}
