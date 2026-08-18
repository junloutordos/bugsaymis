<?php

namespace Tests\Feature\ClassRecord;

use App\Console\Commands\ClassRecord\ConsolidatePehmCoTeaching;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordTeacher;
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

    private function makeClassRecord(array $overrides): ClassRecord
    {
        $sy = $this->pehmSchoolYear();

        return ClassRecord::create(array_merge([
            'school_year_id' => $sy->id,
            'school_year' => $sy->name,
            'subject_name' => 'X',
            'year_level_section' => 'Y',
            'status' => 'draft',
        ], $overrides));
    }

    /** @return array{healthRecord:ClassRecord,musicRecord:?ClassRecord,peRecord:ClassRecord,healthTeacher:User,musicTeacher:?User,peTeacher:User} */
    private function seedTwoRecordSectionFixture(GradingOption $option, int $sectionId): array
    {
        $sy = $this->pehmSchoolYear();
        $healthSubject = Subject::create(['code' => 'HLTH1', 'name' => 'Health 1', 'subject_group' => 'PEHM', 'grade_level' => 7, 'subject_type' => 'lecture', 'school_year_id' => $sy->id]);
        $peSubject = Subject::create(['code' => 'PE1', 'name' => 'Physical Education 1', 'subject_group' => 'PEHM', 'grade_level' => 7, 'subject_type' => 'lecture', 'school_year_id' => $sy->id]);

        $healthTeacher = User::factory()->create();
        $peTeacher = User::factory()->create();

        $healthRecord = $this->makeClassRecord(['section_id' => $sectionId, 'subject_id' => $healthSubject->id, 'teacher_id' => $healthTeacher->id, 'grading_option_id' => $option->id]);
        $peRecord = $this->makeClassRecord(['section_id' => $sectionId, 'subject_id' => $peSubject->id, 'teacher_id' => $peTeacher->id, 'grading_option_id' => $option->id]);

        return compact('healthRecord', 'peRecord', 'healthTeacher', 'peTeacher');
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

    public function test_consolidate_section_merges_records_adds_pivot_rows_for_every_teacher_including_canonical_reparents_assessments_and_archives(): void
    {
        $option = GradingOption::create(['name' => 'PEHM 1-3 (Grade 7)']);
        $sa1 = GradingCategory::create(['grading_option_id' => $option->id, 'name' => 'SA (PE)', 'code' => 'SA 1', 'weight' => 0.10, 'max_assessments' => 1, 'subject_id' => null]);
        $sa2 = GradingCategory::create(['grading_option_id' => $option->id, 'name' => 'SA (Health)', 'code' => 'SA 2', 'weight' => 0.10, 'max_assessments' => 1, 'subject_id' => null]);

        ['healthRecord' => $healthRecord, 'peRecord' => $peRecord, 'healthTeacher' => $healthTeacher, 'peTeacher' => $peTeacher] =
            $this->seedTwoRecordSectionFixture($option, sectionId: 999);

        $sa1->update(['subject_id' => $peRecord->subject_id]);
        $sa2->update(['subject_id' => $healthRecord->subject_id]);

        $healthQuarter = ClassRecordQuarter::create(['class_record_id' => $healthRecord->id, 'quarter' => 1]);
        ClassRecordAssessment::create(['class_record_quarter_id' => $healthQuarter->id, 'grading_category_id' => $sa2->id, 'title' => 'Health Q1 Test', 'activity_date' => '2026-08-10', 'max_score' => 50, 'assessment_number' => 1]);

        $peQuarter = ClassRecordQuarter::create(['class_record_id' => $peRecord->id, 'quarter' => 1]);
        ClassRecordAssessment::create(['class_record_quarter_id' => $peQuarter->id, 'grading_category_id' => $sa1->id, 'title' => 'PE Q1 Test', 'activity_date' => '2026-08-11', 'max_score' => 50, 'assessment_number' => 1]);

        $cmd = new ConsolidatePehmCoTeaching();
        $records = ClassRecord::whereIn('id', [$healthRecord->id, $peRecord->id])->with('subject')->get();

        $report = $cmd->consolidateSection($records, $option->fresh(['categories']), commit: true);

        $canonical = ClassRecord::find($report['canonical_id']);
        $this->assertNotNull($canonical);

        $pivotUserIds = ClassRecordTeacher::where('class_record_id', $canonical->id)->pluck('user_id')->all();
        $this->assertContains($healthTeacher->id, $pivotUserIds);
        $this->assertContains($peTeacher->id, $pivotUserIds);

        $otherId = $report['canonical_id'] === $healthRecord->id ? $peRecord->id : $healthRecord->id;
        $this->assertSame('archived', ClassRecord::find($otherId)->status);

        $canonicalQuarter = ClassRecordQuarter::where('class_record_id', $canonical->id)->where('quarter', 1)->first();
        $reparented = ClassRecordAssessment::where('class_record_quarter_id', $canonicalQuarter->id)->pluck('title')->sort()->values()->all();
        $this->assertSame(['Health Q1 Test', 'PE Q1 Test'], $reparented);

        $this->assertSame(2, $report['pivot_rows_created']);
        $this->assertSame(2, $report['assessments_reparented']);
        $this->assertSame([], $report['unmatched_leaves']);
    }

    public function test_consolidate_section_dry_run_makes_zero_writes(): void
    {
        $option = GradingOption::create(['name' => 'PEHM 1-3 (Grade 7)']);
        GradingCategory::create(['grading_option_id' => $option->id, 'name' => 'SA (PE)', 'code' => 'SA 1', 'weight' => 0.10, 'max_assessments' => 1]);
        GradingCategory::create(['grading_option_id' => $option->id, 'name' => 'SA (Health)', 'code' => 'SA 2', 'weight' => 0.10, 'max_assessments' => 1]);
        $this->seedTwoRecordSectionFixture($option, sectionId: 998);

        $before = [
            'class_records' => \DB::table('class_records')->count(),
            'class_record_teachers' => \DB::table('class_record_teachers')->count(),
            'class_record_assessments' => \DB::table('class_record_assessments')->count(),
        ];

        $cmd = new ConsolidatePehmCoTeaching();
        $records = ClassRecord::where('section_id', 998)->with('subject')->get();
        $report = $cmd->consolidateSection($records, $option->fresh(['categories']), commit: false);

        $this->assertSame($before['class_records'], \DB::table('class_records')->count());
        $this->assertSame($before['class_record_teachers'], \DB::table('class_record_teachers')->count());
        $this->assertSame($before['class_record_assessments'], \DB::table('class_record_assessments')->count());
        $this->assertSame(2, $report['pivot_rows_created']);
    }

    public function test_consolidate_section_flags_an_assessment_whose_old_leaf_has_no_role_match_instead_of_dropping_it_silently(): void
    {
        $sy = $this->pehmSchoolYear();
        $option = GradingOption::create(['name' => 'PEHM 1-3 (Grade 7)']);
        $orphanLeaf = GradingCategory::create(['grading_option_id' => $option->id, 'name' => 'Unlabeled Leaf', 'code' => 'XX 9', 'weight' => 0.10, 'max_assessments' => 1]);

        $healthSubject = Subject::create(['code' => 'HLTH1', 'name' => 'Health 1', 'subject_group' => 'PEHM', 'grade_level' => 7, 'subject_type' => 'lecture', 'school_year_id' => $sy->id]);
        $peSubject = Subject::create(['code' => 'PE1', 'name' => 'Physical Education 1', 'subject_group' => 'PEHM', 'grade_level' => 7, 'subject_type' => 'lecture', 'school_year_id' => $sy->id]);
        $healthRecord = $this->makeClassRecord(['section_id' => 997, 'subject_id' => $healthSubject->id, 'teacher_id' => User::factory()->create()->id, 'grading_option_id' => $option->id]);
        $peRecord = $this->makeClassRecord(['section_id' => 997, 'subject_id' => $peSubject->id, 'teacher_id' => User::factory()->create()->id, 'grading_option_id' => $option->id]);
        $quarter = ClassRecordQuarter::create(['class_record_id' => $peRecord->id, 'quarter' => 1]);
        ClassRecordAssessment::create(['class_record_quarter_id' => $quarter->id, 'grading_category_id' => $orphanLeaf->id, 'title' => 'Mystery Assessment', 'activity_date' => '2026-08-10', 'max_score' => 50, 'assessment_number' => 1]);

        $cmd = new ConsolidatePehmCoTeaching();
        $records = ClassRecord::whereIn('id', [$healthRecord->id, $peRecord->id])->with('subject')->get();
        $report = $cmd->consolidateSection($records, $option->fresh(['categories']), commit: true);

        $this->assertCount(1, $report['unmatched_leaves']);
        $this->assertStringContainsString('Mystery Assessment', $report['unmatched_leaves'][0]);
        $this->assertSame($quarter->id, ClassRecordAssessment::where('title', 'Mystery Assessment')->first()->class_record_quarter_id);
    }
}
