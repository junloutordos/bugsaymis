<?php

namespace Tests\Feature\ClassRecord;

use App\Console\Commands\ClassRecord\ConsolidatePehmCoTeaching;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
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

    public function test_resolve_target_option_for_grade_clones_the_template_per_grade_with_correct_subject_ids_and_does_not_touch_the_original(): void
    {
        $subjects = $this->makePehmSubjects();
        $template = GradingOption::create(['name' => 'PEHM 1-3']);
        $sa = GradingCategory::create(['grading_option_id' => $template->id, 'name' => 'Summative Assessment', 'code' => 'SA', 'weight' => 0.30, 'max_assessments' => 1]);
        GradingCategory::create(['grading_option_id' => $template->id, 'parent_id' => $sa->id, 'name' => 'SA (PE)', 'code' => 'SA 1', 'weight' => 0.10, 'max_assessments' => 1]);
        GradingCategory::create(['grading_option_id' => $template->id, 'parent_id' => $sa->id, 'name' => 'SA (Health)', 'code' => 'SA 2', 'weight' => 0.10, 'max_assessments' => 1]);
        GradingCategory::create(['grading_option_id' => $template->id, 'parent_id' => $sa->id, 'name' => 'SA (Music)', 'code' => 'SA 3', 'weight' => 0.10, 'max_assessments' => 1]);

        $cmd = new ConsolidatePehmCoTeaching();

        $grade7Option = $cmd->resolveTargetOptionForGrade($template, 7, $subjects[7], commit: true);

        $this->assertNotSame($template->id, $grade7Option->id);
        $this->assertSame('PEHM 1-3 (Grade 7)', $grade7Option->name);

        $leaves = $grade7Option->categories()->whereNotNull('parent_id')->get()->keyBy('code');
        $this->assertSame($subjects[7]['PE'], $leaves['SA 1']->subject_id);
        $this->assertSame($subjects[7]['Health'], $leaves['SA 2']->subject_id);
        $this->assertSame($subjects[7]['Music'], $leaves['SA 3']->subject_id);

        $template->refresh();
        $this->assertTrue($template->categories()->whereNotNull('parent_id')->get()->pluck('subject_id')->filter()->isEmpty());
    }

    public function test_resolve_target_option_for_grade_dry_run_makes_zero_writes(): void
    {
        $subjects = $this->makePehmSubjects();
        $template = GradingOption::create(['name' => 'PEHM 1-3']);
        GradingCategory::create(['grading_option_id' => $template->id, 'name' => 'SA (PE)', 'code' => 'SA 1', 'weight' => 0.10, 'max_assessments' => 1]);

        $cmd = new ConsolidatePehmCoTeaching();
        $before = \DB::table('grading_options')->count();

        $result = $cmd->resolveTargetOptionForGrade($template, 7, $subjects[7], commit: false);

        $this->assertSame($before, \DB::table('grading_options')->count());
        $this->assertSame('PEHM 1-3 (Grade 7)', $result->name);
    }

    public function test_resolve_target_option_for_grade_10_mutates_the_existing_template_in_place_no_clone(): void
    {
        $sy = $this->pehmSchoolYear();
        $health4 = Subject::create(['code' => 'HLTH4', 'name' => 'Health 4', 'subject_group' => 'PEHM', 'grade_level' => 10, 'subject_type' => 'lecture', 'school_year_id' => $sy->id]);
        $music4 = Subject::create(['code' => 'MUS4', 'name' => 'Music 4', 'subject_group' => 'PEHM', 'grade_level' => 10, 'subject_type' => 'lecture', 'school_year_id' => $sy->id]);
        $pe4 = Subject::create(['code' => 'PE4', 'name' => 'Physical Education 4', 'subject_group' => 'PEHM', 'grade_level' => 10, 'subject_type' => 'lecture', 'school_year_id' => $sy->id]);
        $subjectsByRole = ['Health' => $health4->id, 'Music' => $music4->id, 'PE' => $pe4->id];

        $template = GradingOption::create(['name' => 'PEHM 4 Final']);
        $leaf = GradingCategory::create(['grading_option_id' => $template->id, 'name' => 'AA (PE)', 'code' => 'AA 1', 'weight' => 0.10, 'max_assessments' => 1]);

        $cmd = new ConsolidatePehmCoTeaching();

        $result = $cmd->resolveTargetOptionForGrade($template, 10, $subjectsByRole, commit: true, cloneEvenIfSingleGrade: false);

        $this->assertSame($template->id, $result->id);
        $this->assertSame($pe4->id, $leaf->refresh()->subject_id);
    }

    public function test_assert_option_only_used_by_pehm_throws_when_referenced_by_an_unexpected_class_record(): void
    {
        $sy = $this->pehmSchoolYear();
        $option = GradingOption::create(['name' => 'PEHM 4 Final']);
        $t1 = User::factory()->create();
        $t2 = User::factory()->create();
        $expectedRecord = ClassRecord::create(['school_year_id' => $sy->id, 'teacher_id' => $t1->id, 'grading_option_id' => $option->id, 'status' => 'draft', 'school_year' => $sy->name, 'subject_name' => 'X', 'year_level_section' => 'Y']);
        $unexpectedRecord = ClassRecord::create(['school_year_id' => $sy->id, 'teacher_id' => $t2->id, 'grading_option_id' => $option->id, 'status' => 'draft', 'school_year' => $sy->name, 'subject_name' => 'X', 'year_level_section' => 'Y']);

        $cmd = new ConsolidatePehmCoTeaching();

        $this->expectException(\RuntimeException::class);
        $cmd->assertOptionOnlyUsedByPehm($option, [$expectedRecord->id]);
    }

    public function test_assert_option_only_used_by_pehm_passes_when_every_referencing_record_is_expected(): void
    {
        $sy = $this->pehmSchoolYear();
        $option = GradingOption::create(['name' => 'PEHM 4 Final']);
        $t1 = User::factory()->create();
        $t2 = User::factory()->create();
        $expectedRecord = ClassRecord::create(['school_year_id' => $sy->id, 'teacher_id' => $t1->id, 'grading_option_id' => $option->id, 'status' => 'draft', 'school_year' => $sy->name, 'subject_name' => 'X', 'year_level_section' => 'Y']);
        $anotherExpectedRecord = ClassRecord::create(['school_year_id' => $sy->id, 'teacher_id' => $t2->id, 'grading_option_id' => $option->id, 'status' => 'draft', 'school_year' => $sy->name, 'subject_name' => 'X', 'year_level_section' => 'Y']);

        $cmd = new ConsolidatePehmCoTeaching();
        $cmd->assertOptionOnlyUsedByPehm($option, [$expectedRecord->id, $anotherExpectedRecord->id]);

        $this->assertTrue(true); // reached without throwing
    }
}
