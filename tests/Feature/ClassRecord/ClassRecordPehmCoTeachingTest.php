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
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
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
    private GradingOption $option;
    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $this->option = GradingOption::create(['name' => 'PEHM Grading', 'is_active' => true]);
        $this->section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $this->sy->id,
            'school_year_id' => $this->sy->id, 'is_active' => true,
        ]);
    }

    private function makeSubject(string $name, string $code): Subject
    {
        return Subject::create([
            'school_year_id' => $this->sy->id, 'code' => $code, 'name' => $name,
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'subject_group' => 'PEHM', 'sessions_per_week' => 2,
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

    // ── Co-teacher access: scores ──────────────────────────────────────────────

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
