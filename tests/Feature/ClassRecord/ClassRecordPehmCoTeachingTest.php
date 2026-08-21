<?php

namespace Tests\Feature\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordAttendanceDate;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordScore;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\ClassRecord\ClassRecordTeacher;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PEHM (Physical Education / Health / Music) shared class record: three
 * subjects, one class record per section, one teacher per subject via the
 * class_record_teachers pivot. Covers co-teacher read/write access, subject
 * -scoped isolation between the three teachers, the join endpoint, and that
 * a normal (non-shared) single-teacher record is completely unaffected.
 */
class ClassRecordPehmCoTeachingTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private AcademicTerm $term;
    private GradingOption $option;
    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $this->term = AcademicTerm::create([
            'school_year_id' => $this->sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $this->option = GradingOption::create(['name' => 'PEHM Grading', 'is_active' => true]);
        $this->section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $this->sy->id,
            'school_year_id' => $this->sy->id, 'is_active' => true,
        ]);
    }

    private function makeSubject(string $name, string $code, int $gradeLevel = 8): Subject
    {
        return Subject::create([
            'school_year_id' => $this->sy->id, 'code' => $code, 'name' => $name,
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => $gradeLevel, 'subject_group' => 'PEHM', 'sessions_per_week' => 2,
            'minutes_per_session' => 60, 'is_active' => true,
        ]);
    }

    private function makeRecord(User $teacher, Subject $subject): ClassRecord
    {
        return ClassRecord::create([
            'subject_id' => $subject->id, 'section_id' => $this->section->id,
            'grading_option_id' => $this->option->id, 'school_year_id' => $this->sy->id,
            'school_year' => $this->sy->name, 'subject_name' => $subject->name,
            'year_level_section' => "G-{$this->section->levelid} {$this->section->sectionname}",
            'teacher_id' => $teacher->id, 'status' => 'draft',
        ]);
    }

    private function makeQuarter(ClassRecord $record, int $q = 1): ClassRecordQuarter
    {
        return ClassRecordQuarter::create([
            'class_record_id' => $record->id, 'grading_option_id' => $this->option->id,
            'quarter' => $q, 'is_locked' => false,
        ]);
    }

    private function attachCoTeacher(ClassRecord $record, Subject $subject, User $user, bool $primary = false): ClassRecordTeacher
    {
        return ClassRecordTeacher::create([
            'class_record_id' => $record->id, 'subject_id' => $subject->id,
            'user_id' => $user->id, 'is_primary' => $primary,
        ]);
    }

    private function admin(): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'class-records.admin'],
            ['module' => 'Class Records', 'description' => 'Admin'],
        );
        $role = Role::create(['name' => 'ClassRecordAdmin_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    private function assignTeachingLoad(User $user, Subject $subject, Section $section): LoadAssignment
    {
        $facultyLoad = FacultyLoad::create([
            'user_id' => $user->id, 'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);

        return LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $user->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id, 'section_id' => $section->id,
            'load_units' => 3,
        ]);
    }

    // ── Co-teacher access: assessments ────────────────────────────────────────

    public function test_co_teacher_can_edit_assessments_under_their_own_subject_leaf(): void
    {
        $peTeacher = User::factory()->create();
        $musicTeacher = User::factory()->create();
        $peSubject = $this->makeSubject('Physical Education 1', 'PE1');
        $musicSubject = $this->makeSubject('Music 1', 'MUS1');

        $record = $this->makeRecord($peTeacher, $peSubject);
        $this->attachCoTeacher($record, $peSubject, $peTeacher, true);
        $this->attachCoTeacher($record, $musicSubject, $musicTeacher);

        $quarter = $this->makeQuarter($record);
        $peLeaf = GradingCategory::create([
            'grading_option_id' => $this->option->id, 'subject_id' => $peSubject->id,
            'name' => 'PE Summative', 'code' => 'PES', 'weight' => 0.5, 'max_assessments' => 5, 'sort_order' => 1,
        ]);

        $response = $this->actingAs($musicTeacher)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $record->id, 'q' => 1]), [
                'assessments' => [[
                    'grading_category_id' => $peLeaf->id,
                    'assessment_number' => 1,
                    'title' => 'PE Quiz 1',
                    'activity_date' => '2025-09-01',
                    'max_score' => 20,
                ]],
            ]);

        // Music teacher must NOT be able to write to the PE leaf.
        $response->assertStatus(403);
    }

    public function test_co_teacher_can_edit_assessments_under_correct_own_subject_leaf(): void
    {
        $peTeacher = User::factory()->create();
        $musicTeacher = User::factory()->create();
        $peSubject = $this->makeSubject('Physical Education 1', 'PE1');
        $musicSubject = $this->makeSubject('Music 1', 'MUS1');

        $record = $this->makeRecord($peTeacher, $peSubject);
        $this->attachCoTeacher($record, $peSubject, $peTeacher, true);
        $this->attachCoTeacher($record, $musicSubject, $musicTeacher);

        $quarter = $this->makeQuarter($record);
        $musicLeaf = GradingCategory::create([
            'grading_option_id' => $this->option->id, 'subject_id' => $musicSubject->id,
            'name' => 'Music Summative', 'code' => 'MUS', 'weight' => 0.5, 'max_assessments' => 5, 'sort_order' => 1,
        ]);

        $this->actingAs($musicTeacher)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $record->id, 'q' => 1]), [
                'assessments' => [[
                    'grading_category_id' => $musicLeaf->id,
                    'assessment_number' => 1,
                    'title' => 'Music Quiz 1',
                    'activity_date' => now()->addMonth()->toDateString(),
                    'max_score' => 20,
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('class_record_assessments', [
            'class_record_quarter_id' => $quarter->id,
            'grading_category_id' => $musicLeaf->id,
            'title' => 'Music Quiz 1',
        ]);
    }

    public function test_co_teacher_can_save_own_assessment_when_payload_also_includes_already_plotted_sibling_subject_assessment(): void
    {
        $peTeacher = User::factory()->create();
        $musicTeacher = User::factory()->create();
        $peSubject = $this->makeSubject('Physical Education 1', 'PE1');
        $musicSubject = $this->makeSubject('Music 1', 'MUS1');

        $record = $this->makeRecord($peTeacher, $peSubject);
        $this->attachCoTeacher($record, $peSubject, $peTeacher, true);
        $this->attachCoTeacher($record, $musicSubject, $musicTeacher);

        $quarter = $this->makeQuarter($record);
        $peLeaf = GradingCategory::create([
            'grading_option_id' => $this->option->id, 'subject_id' => $peSubject->id,
            'name' => 'PE Summative', 'code' => 'PES', 'weight' => 0.5, 'max_assessments' => 5, 'sort_order' => 1,
        ]);
        $musicLeaf = GradingCategory::create([
            'grading_option_id' => $this->option->id, 'subject_id' => $musicSubject->id,
            'name' => 'Music Summative', 'code' => 'MUS', 'weight' => 0.5, 'max_assessments' => 5, 'sort_order' => 2,
        ]);

        // PE teacher already plotted their own assessment first — the normal
        // real-world sequence: the record creator plots their subject, then a
        // co-teacher joins and tries to plot theirs.
        $peAssessment = ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $peLeaf->id,
            'assessment_number' => 1, 'title' => 'PE Quiz 1', 'assessment_type' => 'formative',
            'is_graded' => true, 'is_major' => false, 'activity_date' => now()->addMonth()->toDateString(),
            'max_score' => 20, 'sort_order' => 1,
        ]);

        // The Setup tab always resubmits every leaf's rows on save — the music
        // teacher's payload includes their own new row AND the PE teacher's
        // already-saved row, unchanged.
        $this->actingAs($musicTeacher)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $record->id, 'q' => 1]), [
                'assessments' => [
                    [
                        'id' => $peAssessment->id,
                        'grading_category_id' => $peLeaf->id,
                        'assessment_number' => 1,
                        'title' => 'PE Quiz 1',
                        'activity_date' => $peAssessment->activity_date->toDateString(),
                        'max_score' => 20,
                    ],
                    [
                        'grading_category_id' => $musicLeaf->id,
                        'assessment_number' => 1,
                        'title' => 'Music Quiz 1',
                        'activity_date' => now()->addMonth()->toDateString(),
                        'max_score' => 20,
                    ],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('class_record_assessments', [
            'class_record_quarter_id' => $quarter->id,
            'grading_category_id' => $musicLeaf->id,
            'title' => 'Music Quiz 1',
        ]);
        // The PE teacher's already-plotted row must survive untouched, not be
        // deleted as "missing from the incoming payload".
        $this->assertDatabaseHas('class_record_assessments', [
            'id' => $peAssessment->id,
            'title' => 'PE Quiz 1',
        ]);
    }

    public function test_apply_setup_on_shared_record_copies_only_the_co_teachers_subject_and_preserves_other_subjects(): void
    {
        $peTeacher = User::factory()->create();
        $musicTeacher = User::factory()->create();
        $peSubject = $this->makeSubject('Physical Education 1', 'PE1');
        $musicSubject = $this->makeSubject('Music 1', 'MUS1');

        $peLeaf = GradingCategory::create([
            'grading_option_id' => $this->option->id,
            'subject_id' => $peSubject->id,
            'name' => 'PE Summative',
            'code' => 'PES',
            'weight' => 0.5,
            'max_assessments' => 5,
            'sort_order' => 1,
        ]);
        $musicLeaf = GradingCategory::create([
            'grading_option_id' => $this->option->id,
            'subject_id' => $musicSubject->id,
            'name' => 'Music Summative',
            'code' => 'MUS',
            'weight' => 0.5,
            'max_assessments' => 5,
            'sort_order' => 2,
        ]);

        $source = $this->makeRecord($peTeacher, $peSubject);
        $this->attachCoTeacher($source, $peSubject, $peTeacher, true);
        $this->attachCoTeacher($source, $musicSubject, $musicTeacher);
        $sourceQuarter = $this->makeQuarter($source);

        foreach ([[$peLeaf, 'PE Quiz'], [$musicLeaf, 'Music Quiz']] as [$category, $title]) {
            ClassRecordAssessment::create([
                'class_record_quarter_id' => $sourceQuarter->id,
                'grading_category_id' => $category->id,
                'assessment_number' => 1,
                'title' => $title,
                'assessment_type' => 'alternative',
                'is_graded' => true,
                'is_major' => false,
                'activity_date' => '2026-09-07',
                'max_score' => 20,
                'sort_order' => 1,
            ]);
        }

        $targetSection = Section::create([
            'levelid' => 8,
            'sectionname' => 'Ruby',
            'syid' => $this->sy->id,
            'school_year_id' => $this->sy->id,
            'is_active' => true,
        ]);
        $target = ClassRecord::create([
            'subject_id' => $peSubject->id,
            'section_id' => $targetSection->id,
            'grading_option_id' => $this->option->id,
            'school_year_id' => $this->sy->id,
            'school_year' => $this->sy->name,
            'subject_name' => $peSubject->name,
            'year_level_section' => 'G-8 Ruby',
            'teacher_id' => $peTeacher->id,
            'status' => 'draft',
        ]);
        $this->attachCoTeacher($target, $peSubject, $peTeacher, true);
        $this->attachCoTeacher($target, $musicSubject, $musicTeacher);
        $targetQuarter = $this->makeQuarter($target);
        $existingPe = ClassRecordAssessment::create([
            'class_record_quarter_id' => $targetQuarter->id,
            'grading_category_id' => $peLeaf->id,
            'assessment_number' => 1,
            'title' => 'Existing PE Quiz',
            'assessment_type' => 'alternative',
            'is_graded' => true,
            'is_major' => false,
            'activity_date' => '2026-09-08',
            'max_score' => 20,
            'sort_order' => 1,
        ]);

        $this->actingAs($musicTeacher)
            ->postJson(route('class-records.assessments.apply-to-sections', [
                'classRecord' => $source->id,
                'q' => 1,
            ]), ['target_class_record_ids' => [$target->id]])
            ->assertOk()
            ->assertJsonCount(1, 'applied')
            ->assertJsonPath('applied.0.count', 1);

        $this->assertDatabaseHas('class_record_assessments', [
            'id' => $existingPe->id,
            'title' => 'Existing PE Quiz',
        ]);
        $this->assertDatabaseHas('class_record_assessments', [
            'class_record_quarter_id' => $targetQuarter->id,
            'grading_category_id' => $musicLeaf->id,
            'title' => 'Music Quiz',
        ]);
        $this->assertDatabaseMissing('class_record_assessments', [
            'class_record_quarter_id' => $targetQuarter->id,
            'title' => 'PE Quiz',
        ]);
    }

    // ── Schedule-day check is scoped to each co-teacher's OWN subject ─────────
    // Regression coverage for the reported bug: a co-teacher's assessment
    // plotting was being validated against the record CREATOR's subject
    // schedule instead of their own — so a PE-only Tuesday class looked
    // "closed" to the Music co-teacher whose actual schedule is Wednesday,
    // and vice versa.

    private function scheduleFor(User $teacher, Subject $subject, Section $section, string $dayOfWeek): ClassSchedule
    {
        return ClassSchedule::create([
            'user_id' => $teacher->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'school_year_id' => $this->sy->id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => $dayOfWeek,
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'status' => 'active',
        ]);
    }

    public function test_co_teacher_can_plot_on_their_own_subjects_scheduled_day_even_when_it_differs_from_the_record_creators(): void
    {
        $peTeacher = User::factory()->create();
        $musicTeacher = User::factory()->create();
        $peSubject = $this->makeSubject('Physical Education 1', 'PE1');
        $musicSubject = $this->makeSubject('Music 1', 'MUS1');

        // PE (the record's creator/default subject) meets Tuesday; Music (the
        // co-teacher's subject) meets Wednesday — genuinely different days,
        // as PEHM subjects normally do.
        $this->scheduleFor($peTeacher, $peSubject, $this->section, 'Tuesday');
        $this->scheduleFor($musicTeacher, $musicSubject, $this->section, 'Wednesday');

        $record = $this->makeRecord($peTeacher, $peSubject);
        $this->attachCoTeacher($record, $peSubject, $peTeacher, true);
        $this->attachCoTeacher($record, $musicSubject, $musicTeacher);

        $quarter = $this->makeQuarter($record);
        $musicLeaf = GradingCategory::create([
            'grading_option_id' => $this->option->id, 'subject_id' => $musicSubject->id,
            'name' => 'Music Summative', 'code' => 'MUS', 'weight' => 0.5, 'max_assessments' => 5, 'sort_order' => 1,
        ]);

        // Next Wednesday — Music's own scheduled day, but NOT PE's (Tuesday).
        // Before the fix, the record's default (PE) subject_id was used for
        // the schedule check, which would incorrectly reject this date.
        $wednesday = now()->addMonth()->next(\Carbon\Carbon::WEDNESDAY)->toDateString();

        $this->actingAs($musicTeacher)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $record->id, 'q' => 1]), [
                'assessments' => [[
                    'grading_category_id' => $musicLeaf->id,
                    'assessment_number' => 1,
                    'title' => 'Music Quiz 1',
                    'activity_date' => $wednesday,
                    'max_score' => 20,
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('class_record_assessments', [
            'class_record_quarter_id' => $quarter->id,
            'grading_category_id' => $musicLeaf->id,
            'title' => 'Music Quiz 1',
            'activity_date' => $wednesday,
        ]);
    }

    public function test_co_teacher_is_still_blocked_on_a_day_their_own_subject_does_not_meet(): void
    {
        $peTeacher = User::factory()->create();
        $musicTeacher = User::factory()->create();
        $peSubject = $this->makeSubject('Physical Education 1', 'PE1');
        $musicSubject = $this->makeSubject('Music 1', 'MUS1');

        // PE meets Tuesday; Music meets Wednesday.
        $this->scheduleFor($peTeacher, $peSubject, $this->section, 'Tuesday');
        $this->scheduleFor($musicTeacher, $musicSubject, $this->section, 'Wednesday');

        $record = $this->makeRecord($peTeacher, $peSubject);
        $this->attachCoTeacher($record, $peSubject, $peTeacher, true);
        $this->attachCoTeacher($record, $musicSubject, $musicTeacher);

        $quarter = $this->makeQuarter($record);
        $musicLeaf = GradingCategory::create([
            'grading_option_id' => $this->option->id, 'subject_id' => $musicSubject->id,
            'name' => 'Music Summative', 'code' => 'MUS', 'weight' => 0.5, 'max_assessments' => 5, 'sort_order' => 1,
        ]);

        // Next Tuesday — PE's day, but Music has no class this day, so the
        // Music co-teacher must still be blocked (schedule check must not
        // become a no-op — it should just use the RIGHT subject).
        $tuesday = now()->addMonth()->next(\Carbon\Carbon::TUESDAY)->toDateString();

        $response = $this->actingAs($musicTeacher)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $record->id, 'q' => 1]), [
                'assessments' => [[
                    'grading_category_id' => $musicLeaf->id,
                    'assessment_number' => 1,
                    'title' => 'Music Quiz 1',
                    'activity_date' => $tuesday,
                    'max_score' => 20,
                ]],
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('class_record_assessments', [
            'class_record_quarter_id' => $quarter->id,
            'grading_category_id' => $musicLeaf->id,
        ]);
    }

    public function test_calendar_plot_endpoint_uses_co_teachers_own_subject_schedule_not_the_record_creators(): void
    {
        $peTeacher = User::factory()->create();
        $musicTeacher = User::factory()->create();
        $peSubject = $this->makeSubject('Physical Education 1', 'PE1');
        $musicSubject = $this->makeSubject('Music 1', 'MUS1');

        $this->scheduleFor($peTeacher, $peSubject, $this->section, 'Tuesday');
        $this->scheduleFor($musicTeacher, $musicSubject, $this->section, 'Wednesday');

        $record = $this->makeRecord($peTeacher, $peSubject);
        $this->attachCoTeacher($record, $peSubject, $peTeacher, true);
        $this->attachCoTeacher($record, $musicSubject, $musicTeacher);

        $this->makeQuarter($record);
        $musicLeaf = GradingCategory::create([
            'grading_option_id' => $this->option->id, 'subject_id' => $musicSubject->id,
            'name' => 'Music Summative', 'code' => 'MUS', 'weight' => 0.5, 'max_assessments' => 5, 'sort_order' => 1,
        ]);

        $wednesday = now()->addMonth()->next(\Carbon\Carbon::WEDNESDAY)->toDateString();

        $this->actingAs($musicTeacher)
            ->postJson(route('class-records.assessments.plot', ['classRecord' => $record->id, 'q' => 1]), [
                'grading_category_id' => $musicLeaf->id,
                'title' => 'Music Quiz via Calendar',
                'activity_date' => $wednesday,
                'max_score' => 20,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('class_record_assessments', [
            'grading_category_id' => $musicLeaf->id,
            'title' => 'Music Quiz via Calendar',
            'activity_date' => $wednesday,
        ]);
    }


    public function test_co_teacher_cannot_enter_scores_for_a_leaf_they_do_not_own(): void
    {
        $peTeacher = User::factory()->create();
        $healthTeacher = User::factory()->create();
        $peSubject = $this->makeSubject('Physical Education 1', 'PE1');
        $healthSubject = $this->makeSubject('Health 1', 'HEA1');

        $record = $this->makeRecord($peTeacher, $peSubject);
        $this->attachCoTeacher($record, $peSubject, $peTeacher, true);
        $this->attachCoTeacher($record, $healthSubject, $healthTeacher);

        $quarter = $this->makeQuarter($record);
        $peLeaf = GradingCategory::create([
            'grading_option_id' => $this->option->id, 'subject_id' => $peSubject->id,
            'name' => 'PE Summative', 'code' => 'PES', 'weight' => 0.5, 'max_assessments' => 5, 'sort_order' => 1,
        ]);
        $assessment = ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $peLeaf->id,
            'assessment_number' => 1, 'title' => 'PE Quiz 1', 'assessment_type' => 'formative',
            'is_graded' => true, 'is_major' => false, 'max_score' => 20, 'sort_order' => 1,
        ]);
        $student = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'Doe', 'given_name' => 'Jane',
            'sequence_number' => 1, 'is_active' => true,
        ]);

        $this->actingAs($healthTeacher)
            ->postJson(route('class-records.scores.upsert', ['classRecord' => $record->id, 'q' => 1]), [
                'scores' => [['student_id' => $student->id, 'assessment_id' => $assessment->id, 'score' => 18]],
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('class_record_scores', [
            'class_record_student_id' => $student->id,
            'class_record_assessment_id' => $assessment->id,
        ]);
    }

    public function test_co_teacher_can_enter_scores_for_their_own_leaf(): void
    {
        $peTeacher = User::factory()->create();
        $healthTeacher = User::factory()->create();
        $peSubject = $this->makeSubject('Physical Education 1', 'PE1');
        $healthSubject = $this->makeSubject('Health 1', 'HEA1');

        $record = $this->makeRecord($peTeacher, $peSubject);
        $this->attachCoTeacher($record, $peSubject, $peTeacher, true);
        $this->attachCoTeacher($record, $healthSubject, $healthTeacher);

        $quarter = $this->makeQuarter($record);
        $healthLeaf = GradingCategory::create([
            'grading_option_id' => $this->option->id, 'subject_id' => $healthSubject->id,
            'name' => 'Health Summative', 'code' => 'HES', 'weight' => 0.5, 'max_assessments' => 5, 'sort_order' => 1,
        ]);
        $assessment = ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $healthLeaf->id,
            'assessment_number' => 1, 'title' => 'Health Quiz 1', 'assessment_type' => 'formative',
            'is_graded' => true, 'is_major' => false, 'max_score' => 20, 'sort_order' => 1,
        ]);
        $student = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'Doe', 'given_name' => 'Jane',
            'sequence_number' => 1, 'is_active' => true,
        ]);

        $this->actingAs($healthTeacher)
            ->postJson(route('class-records.scores.upsert', ['classRecord' => $record->id, 'q' => 1]), [
                'scores' => [['student_id' => $student->id, 'assessment_id' => $assessment->id, 'score' => 18]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('class_record_scores', [
            'class_record_student_id' => $student->id,
            'class_record_assessment_id' => $assessment->id,
            'score' => 18,
        ]);
    }

    // ── Co-teacher access: attendance (independent per subject) ───────────────

    public function test_attendance_dates_are_independent_per_subject_on_a_shared_record(): void
    {
        $peTeacher = User::factory()->create();
        $musicTeacher = User::factory()->create();
        $peSubject = $this->makeSubject('Physical Education 1', 'PE1');
        $musicSubject = $this->makeSubject('Music 1', 'MUS1');

        $record = $this->makeRecord($peTeacher, $peSubject);
        $this->attachCoTeacher($record, $peSubject, $peTeacher, true);
        $this->attachCoTeacher($record, $musicSubject, $musicTeacher);
        $quarter = $this->makeQuarter($record);

        // PE teacher adds a PE-only date.
        $this->actingAs($peTeacher)
            ->postJson(route('class-records.attendance.store-dates', ['classRecord' => $record->id, 'q' => 1]), [
                'date' => '2025-09-02', 'subject_id' => $peSubject->id,
            ])
            ->assertOk();

        // Music teacher adds a Music-only date (same calendar day is allowed —
        // independent per subject).
        $this->actingAs($musicTeacher)
            ->postJson(route('class-records.attendance.store-dates', ['classRecord' => $record->id, 'q' => 1]), [
                'date' => '2025-09-02', 'subject_id' => $musicSubject->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('class_record_attendance_dates', [
            'class_record_quarter_id' => $quarter->id, 'subject_id' => $peSubject->id, 'date' => '2025-09-02',
        ]);
        $this->assertDatabaseHas('class_record_attendance_dates', [
            'class_record_quarter_id' => $quarter->id, 'subject_id' => $musicSubject->id, 'date' => '2025-09-02',
        ]);
        $this->assertEquals(2, ClassRecordAttendanceDate::where('class_record_quarter_id', $quarter->id)->count());
    }

    public function test_co_teacher_cannot_add_attendance_date_for_a_subject_they_do_not_own(): void
    {
        $peTeacher = User::factory()->create();
        $musicTeacher = User::factory()->create();
        $peSubject = $this->makeSubject('Physical Education 1', 'PE1');
        $musicSubject = $this->makeSubject('Music 1', 'MUS1');

        $record = $this->makeRecord($peTeacher, $peSubject);
        $this->attachCoTeacher($record, $peSubject, $peTeacher, true);
        $this->attachCoTeacher($record, $musicSubject, $musicTeacher);
        $this->makeQuarter($record);

        $this->actingAs($musicTeacher)
            ->postJson(route('class-records.attendance.store-dates', ['classRecord' => $record->id, 'q' => 1]), [
                'date' => '2025-09-02', 'subject_id' => $peSubject->id,
            ])
            ->assertStatus(403);
    }

    public function test_co_teacher_cannot_write_attendance_records_for_another_subjects_date(): void
    {
        $peTeacher = User::factory()->create();
        $musicTeacher = User::factory()->create();
        $peSubject = $this->makeSubject('Physical Education 1', 'PE1');
        $musicSubject = $this->makeSubject('Music 1', 'MUS1');

        $record = $this->makeRecord($peTeacher, $peSubject);
        $this->attachCoTeacher($record, $peSubject, $peTeacher, true);
        $this->attachCoTeacher($record, $musicSubject, $musicTeacher);
        $quarter = $this->makeQuarter($record);

        $peDate = ClassRecordAttendanceDate::create([
            'class_record_quarter_id' => $quarter->id, 'subject_id' => $peSubject->id,
            'date' => '2025-09-02', 'sort_order' => 1,
        ]);
        $student = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'Doe', 'given_name' => 'Jane',
            'sequence_number' => 1, 'is_active' => true,
        ]);

        // Music teacher tries to write into the PE date by claiming subject_id=music
        // but referencing the PE date_id — must be rejected as not belonging to
        // the resolved subject scope.
        $this->actingAs($musicTeacher)
            ->postJson(route('class-records.attendance.upsert', ['classRecord' => $record->id, 'q' => 1]), [
                'subject_id' => $musicSubject->id,
                'records' => [['date_id' => $peDate->id, 'student_id' => $student->id, 'status' => 'absent']],
            ])
            ->assertStatus(422);
    }

    // ── Join flow ───────────────────────────────────────────────────────────

    public function test_teacher_can_join_an_existing_shared_record_for_their_own_subject(): void
    {
        $peTeacher = User::factory()->create();
        $healthTeacher = User::factory()->create();
        $peSubject = $this->makeSubject('Physical Education 1', 'PE1');
        $healthSubject = $this->makeSubject('Health 1', 'HEA1');

        $record = $this->makeRecord($peTeacher, $peSubject);

        $this->actingAs($healthTeacher)
            ->postJson(route('class-records.join', ['classRecord' => $record->id]), [
                'subject_id' => $healthSubject->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('class_record_teachers', [
            'class_record_id' => $record->id, 'subject_id' => $healthSubject->id, 'user_id' => $healthTeacher->id,
        ]);
        // Primary teacher gets backfilled with their own pivot row too.
        $this->assertDatabaseHas('class_record_teachers', [
            'class_record_id' => $record->id, 'subject_id' => $peSubject->id, 'user_id' => $peTeacher->id,
        ]);

        $record->refresh();
        $this->assertStringContainsString('PEHM', $record->subject_name);
    }

    public function test_synced_shared_label_uses_concise_pehm_grade_number_not_subject_name(): void
    {
        $peTeacher = User::factory()->create();
        $healthTeacher = User::factory()->create();
        // grade_level 8 -> the PEHM group's own "2" (7=1, 8=2, 9=3, 10=4).
        $peSubject = $this->makeSubject('Physical Education 2', 'PE2', 8);
        $healthSubject = $this->makeSubject('Health 2', 'HEA2', 8);

        $record = $this->makeRecord($peTeacher, $peSubject);

        $this->actingAs($healthTeacher)
            ->postJson(route('class-records.join', ['classRecord' => $record->id]), [
                'subject_id' => $healthSubject->id,
            ])
            ->assertOk();

        $record->refresh();
        $this->assertSame('PEHM 2', $record->subject_name);
    }

    public function test_synced_shared_label_maps_grade_level_to_matching_pehm_number(): void
    {
        $peTeacher = User::factory()->create();
        $musicTeacher = User::factory()->create();
        $peSubject = $this->makeSubject('Physical Education 1', 'PE1G7', 7);
        $musicSubject = $this->makeSubject('Music 1', 'MUS1G7', 7);

        $record = $this->makeRecord($peTeacher, $peSubject);

        $this->actingAs($musicTeacher)
            ->postJson(route('class-records.join', ['classRecord' => $record->id]), [
                'subject_id' => $musicSubject->id,
            ])
            ->assertOk();

        $record->refresh();
        $this->assertSame('PEHM 1', $record->subject_name);
    }

    public function test_non_admin_cannot_steal_another_teachers_already_claimed_subject_slot(): void
    {
        $peTeacher = User::factory()->create();
        $healthTeacher = User::factory()->create();
        $intruder = User::factory()->create();
        $peSubject = $this->makeSubject('Physical Education 1', 'PE1');
        $healthSubject = $this->makeSubject('Health 1', 'HEA1');

        $record = $this->makeRecord($peTeacher, $peSubject);
        $this->attachCoTeacher($record, $peSubject, $peTeacher, true);
        $this->attachCoTeacher($record, $healthSubject, $healthTeacher);

        $this->actingAs($intruder)
            ->postJson(route('class-records.join', ['classRecord' => $record->id]), [
                'subject_id' => $healthSubject->id,
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('class_record_teachers', [
            'class_record_id' => $record->id, 'subject_id' => $healthSubject->id, 'user_id' => $healthTeacher->id,
        ]);
    }

    public function test_join_is_idempotent_for_the_same_teacher_rejoining_their_own_subject(): void
    {
        $peTeacher = User::factory()->create();
        $peSubject = $this->makeSubject('Physical Education 1', 'PE1');
        $record = $this->makeRecord($peTeacher, $peSubject);
        $this->attachCoTeacher($record, $peSubject, $peTeacher, true);

        $this->actingAs($peTeacher)
            ->postJson(route('class-records.join', ['classRecord' => $record->id]), [
                'subject_id' => $peSubject->id,
            ])
            ->assertOk();

        $this->assertEquals(1, ClassRecordTeacher::where('class_record_id', $record->id)
            ->where('subject_id', $peSubject->id)->count());
    }

    // ── Co-teacher candidates (admin "Add Co-teacher" lookup) ─────────────────

    public function test_admin_can_list_co_teacher_candidates(): void
    {
        $peTeacher = User::factory()->create();
        $healthTeacher = User::factory()->create();
        $musicTeacher = User::factory()->create();
        $peSubject = $this->makeSubject('Physical Education 1', 'PE1');
        $healthSubject = $this->makeSubject('Health 1', 'HEA1');
        $musicSubject = $this->makeSubject('Music 1', 'MUS1');

        $record = $this->makeRecord($peTeacher, $peSubject);
        $this->attachCoTeacher($record, $peSubject, $peTeacher, true);
        // Health is already covered by a co-teacher — must be excluded from candidates.
        $this->attachCoTeacher($record, $healthSubject, $healthTeacher);

        $this->assignTeachingLoad($musicTeacher, $musicSubject, $this->section);

        $response = $this->actingAs($this->admin())
            ->getJson(route('class-records.co-teacher-candidates', ['classRecord' => $record->id]))
            ->assertOk();

        $subjects = $response->json('subjects');
        $this->assertCount(1, $subjects);
        $this->assertSame($musicSubject->id, $subjects[0]['id']);

        $teachers = $response->json("teachers_by_subject.{$musicSubject->id}");
        $this->assertCount(1, $teachers);
        $this->assertSame($musicTeacher->id, $teachers[0]['id']);
    }

    public function test_non_admin_cannot_list_co_teacher_candidates(): void
    {
        $peTeacher = User::factory()->create();
        $peSubject = $this->makeSubject('Physical Education 1', 'PE1');
        $record = $this->makeRecord($peTeacher, $peSubject);

        $this->actingAs($peTeacher)
            ->getJson(route('class-records.co-teacher-candidates', ['classRecord' => $record->id]))
            ->assertStatus(403);
    }

    public function test_candidates_endpoint_returns_empty_for_non_shared_subject(): void
    {
        $teacher = User::factory()->create();
        $subject = Subject::create([
            'school_year_id' => $this->sy->id, 'code' => 'MATH8', 'name' => 'Mathematics 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'subject_group' => null, 'sessions_per_week' => 5,
            'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $record = $this->makeRecord($teacher, $subject);

        $response = $this->actingAs($this->admin())
            ->getJson(route('class-records.co-teacher-candidates', ['classRecord' => $record->id]))
            ->assertOk();

        $this->assertSame([], $response->json('subjects'));
    }

    // ── Monitor scope ──────────────────────────────────────────────────────────

    public function test_monitor_can_view_shared_record_via_any_co_teacher(): void
    {
        $peTeacher = User::factory()->create();
        $musicTeacher = User::factory()->create();
        $peSubject = $this->makeSubject('Physical Education 1', 'PE1');
        $musicSubject = $this->makeSubject('Music 1', 'MUS1');

        $record = $this->makeRecord($peTeacher, $peSubject);
        $this->attachCoTeacher($record, $peSubject, $peTeacher, true);
        $this->attachCoTeacher($record, $musicSubject, $musicTeacher);

        $this->assertTrue($record->canEdit($peTeacher));
        $this->assertTrue($record->canEdit($musicTeacher));
        $this->assertFalse($record->canEdit(User::factory()->create()));
    }

    // ── Backward compatibility: a normal single-teacher record ─────────────────

    public function test_normal_single_teacher_record_is_unaffected_by_co_teaching_changes(): void
    {
        $teacher = User::factory()->create();
        $subject = Subject::create([
            'school_year_id' => $this->sy->id, 'code' => 'MATH8', 'name' => 'Mathematics 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'subject_group' => null, 'sessions_per_week' => 5,
            'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $record = $this->makeRecord($teacher, $subject);
        $quarter = $this->makeQuarter($record);

        $this->assertTrue($record->canEdit($teacher));
        $this->assertFalse($record->canEdit(User::factory()->create()));
        $this->assertEquals([$teacher->id], $record->allTeacherIds());
        $this->assertEquals([$teacher->id], $record->teacherIdsFor(null));

        $leaf = GradingCategory::create([
            'grading_option_id' => $this->option->id, 'subject_id' => null,
            'name' => 'Formative', 'code' => 'FA', 'weight' => 1.0, 'max_assessments' => 5, 'sort_order' => 1,
        ]);

        $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $record->id, 'q' => 1]), [
                'assessments' => [[
                    'grading_category_id' => $leaf->id,
                    'assessment_number' => 1,
                    'title' => 'Quiz 1',
                    'activity_date' => now()->addMonth()->toDateString(),
                    'max_score' => 20,
                ]],
            ])
            ->assertOk();

        // Attendance for a normal record still has subject_id = null.
        $this->actingAs($teacher)
            ->postJson(route('class-records.attendance.store-dates', ['classRecord' => $record->id, 'q' => 1]), [
                'date' => '2025-09-01',
            ])
            ->assertOk();

        $this->assertDatabaseHas('class_record_attendance_dates', [
            'class_record_quarter_id' => $quarter->id, 'subject_id' => null, 'date' => '2025-09-01',
        ]);
    }
}
