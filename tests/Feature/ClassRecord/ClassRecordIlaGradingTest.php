<?php

namespace Tests\Feature\ClassRecord;

use App\Console\Commands\ClassRecord\GenerateIlaDates;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordIlaDate;
use App\Models\ClassRecord\ClassRecordIlaRecord;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordScore;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ClassRecord\WatRuleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassRecordIlaGradingTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private GradingOption $option;
    private GradingCategory $category;

    // 2026-08-03 is a Monday; its plotting deadline is Fri 2026-07-31 12:00 NN.
    private const ILA_DATE = '2026-08-03';
    private const BEFORE_DEADLINE = '2026-07-30 09:00:00';
    private const AFTER_DEADLINE  = '2026-07-31 13:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        AcademicTerm::create([
            'school_year_id' => $this->sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2026-01-15', 'is_current' => true,
        ]);
        $this->option = GradingOption::create(['name' => 'Default', 'is_active' => true]);
        $this->category = GradingCategory::create([
            'grading_option_id' => $this->option->id, 'name' => 'Formative', 'code' => 'FA',
            'weight' => 1.0, 'max_assessments' => 10, 'sort_order' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeSection(): Section
    {
        return Section::create([
            'levelid' => 8, 'sectionname' => 'Section-'.uniqid(), 'syid' => $this->sy->id,
            'school_year_id' => $this->sy->id, 'is_active' => true,
        ]);
    }

    private function makeSubject(): Subject
    {
        return Subject::create([
            'school_year_id' => $this->sy->id, 'code' => 'SUBJ-'.uniqid(), 'name' => 'Subject 1',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
            'has_ilp' => true,
        ]);
    }

    private function makeRecord(User $teacher, Section $section, Subject $subject): ClassRecord
    {
        return ClassRecord::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'grading_option_id' => $this->option->id, 'school_year_id' => $this->sy->id,
            'school_year' => $this->sy->name, 'subject_name' => $subject->name,
            'year_level_section' => "G-{$section->levelid} {$section->sectionname}",
            'teacher_id' => $teacher->id, 'status' => 'draft',
        ]);
    }

    private function makeQuarter(ClassRecord $record, bool $locked = false): ClassRecordQuarter
    {
        return ClassRecordQuarter::create([
            'class_record_id' => $record->id, 'grading_option_id' => $this->option->id,
            'quarter' => 1, 'is_locked' => $locked,
        ]);
    }

    private function makeIlaDate(ClassRecordQuarter $quarter, string $date = self::ILA_DATE): ClassRecordIlaDate
    {
        return ClassRecordIlaDate::create([
            'class_record_quarter_id' => $quarter->id, 'date' => $date,
            'sort_order' => 1, 'is_auto_generated' => true,
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

    // ── Grading ────────────────────────────────────────────────────────────────

    public function test_teacher_can_grade_an_ila_date_before_the_deadline(): void
    {
        Carbon::setTestNow(self::BEFORE_DEADLINE);

        $teacher = User::factory()->create();
        $record  = $this->makeRecord($teacher, $this->makeSection(), $this->makeSubject());
        $quarter = $this->makeQuarter($record);
        $ilaDate = $this->makeIlaDate($quarter);

        $student1 = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'Doe', 'given_name' => 'Jane',
            'sequence_number' => 1, 'is_active' => true,
        ]);
        $student2 = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'Roe', 'given_name' => 'Rick',
            'sequence_number' => 2, 'is_active' => true,
        ]);
        ClassRecordIlaRecord::create(['class_record_ila_date_id' => $ilaDate->id, 'class_record_student_id' => $student1->id, 'status' => 'compliant']);
        ClassRecordIlaRecord::create(['class_record_ila_date_id' => $ilaDate->id, 'class_record_student_id' => $student2->id, 'status' => 'non_compliant']);

        $response = $this->actingAs($teacher)
            ->postJson(route('class-records.ila.grade-date', ['classRecord' => $record->id, 'q' => 1, 'ilaDate' => $ilaDate->id]), [
                'grading_category_id' => $this->category->id,
                'title'                => 'ILA — Aug 3',
                'max_score'            => 100,
            ])
            ->assertOk();

        $assessmentId = $response->json('assessment.id');
        $this->assertDatabaseHas('class_record_assessments', [
            'id' => $assessmentId, 'is_graded' => true, 'assessment_type' => 'formative',
            'activity_date' => self::ILA_DATE, 'max_score' => 100,
        ]);
        $this->assertDatabaseHas('class_record_ila_dates', [
            'id' => $ilaDate->id, 'is_graded' => true, 'class_record_assessment_id' => $assessmentId,
        ]);
        $this->assertDatabaseHas('class_record_scores', [
            'class_record_assessment_id' => $assessmentId, 'class_record_student_id' => $student1->id, 'score' => 100,
        ]);
        $this->assertDatabaseHas('class_record_scores', [
            'class_record_assessment_id' => $assessmentId, 'class_record_student_id' => $student2->id, 'score' => 0,
        ]);
    }

    public function test_grading_is_blocked_after_the_friday_noon_deadline(): void
    {
        Carbon::setTestNow(self::AFTER_DEADLINE);

        $teacher = User::factory()->create();
        $record  = $this->makeRecord($teacher, $this->makeSection(), $this->makeSubject());
        $quarter = $this->makeQuarter($record);
        $ilaDate = $this->makeIlaDate($quarter);

        $this->actingAs($teacher)
            ->postJson(route('class-records.ila.grade-date', ['classRecord' => $record->id, 'q' => 1, 'ilaDate' => $ilaDate->id]), [
                'grading_category_id' => $this->category->id,
                'title'                => 'ILA — Aug 3',
                'max_score'            => 100,
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('class_record_ila_dates', ['id' => $ilaDate->id, 'is_graded' => false]);
        $this->assertDatabaseCount('class_record_assessments', 0);
    }

    public function test_admin_may_grade_past_the_deadline(): void
    {
        Carbon::setTestNow(self::AFTER_DEADLINE);

        $admin   = $this->admin();
        $teacher = User::factory()->create();
        $record  = $this->makeRecord($teacher, $this->makeSection(), $this->makeSubject());
        $quarter = $this->makeQuarter($record);
        $ilaDate = $this->makeIlaDate($quarter);

        $this->actingAs($admin)
            ->postJson(route('class-records.ila.grade-date', ['classRecord' => $record->id, 'q' => 1, 'ilaDate' => $ilaDate->id]), [
                'grading_category_id' => $this->category->id,
                'title'                => 'ILA — Aug 3',
                'max_score'            => 100,
            ])
            ->assertOk();

        $this->assertDatabaseHas('class_record_ila_dates', ['id' => $ilaDate->id, 'is_graded' => true]);
    }

    public function test_grading_rejects_a_category_outside_the_quarters_grading_option(): void
    {
        Carbon::setTestNow(self::BEFORE_DEADLINE);

        $otherOption   = GradingOption::create(['name' => 'Other', 'is_active' => true]);
        $foreignCategory = GradingCategory::create([
            'grading_option_id' => $otherOption->id, 'name' => 'Alt', 'code' => 'AA',
            'weight' => 1.0, 'max_assessments' => 5, 'sort_order' => 1,
        ]);

        $teacher = User::factory()->create();
        $record  = $this->makeRecord($teacher, $this->makeSection(), $this->makeSubject());
        $quarter = $this->makeQuarter($record);
        $ilaDate = $this->makeIlaDate($quarter);

        $this->actingAs($teacher)
            ->postJson(route('class-records.ila.grade-date', ['classRecord' => $record->id, 'q' => 1, 'ilaDate' => $ilaDate->id]), [
                'grading_category_id' => $foreignCategory->id,
                'title'                => 'ILA — Aug 3',
                'max_score'            => 100,
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('class_record_assessments', 0);
    }

    public function test_grading_is_blocked_when_it_would_exceed_the_daily_graded_cap(): void
    {
        Carbon::setTestNow(self::BEFORE_DEADLINE);

        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $record  = $this->makeRecord($teacher, $section, $this->makeSubject());
        $quarter = $this->makeQuarter($record);
        $ilaDate = $this->makeIlaDate($quarter);

        // Fill the section's daily graded cap (3) with ordinary assessments
        // on the same date, in a SEPARATE class record for the same section
        // (WAT caps are per-section, pooled across every subject taught to it).
        for ($i = 1; $i <= 3; $i++) {
            $otherRecord  = $this->makeRecord(User::factory()->create(), $section, $this->makeSubject());
            $otherQuarter = $this->makeQuarter($otherRecord);
            ClassRecordAssessment::create([
                'class_record_quarter_id' => $otherQuarter->id, 'grading_category_id' => $this->category->id,
                'assessment_type' => 'formative', 'is_graded' => true, 'is_major' => false,
                'assessment_number' => 1, 'title' => "Filler {$i}", 'activity_date' => self::ILA_DATE,
                'max_score' => 100, 'sort_order' => 1,
            ]);
        }

        $this->actingAs($teacher)
            ->postJson(route('class-records.ila.grade-date', ['classRecord' => $record->id, 'q' => 1, 'ilaDate' => $ilaDate->id]), [
                'grading_category_id' => $this->category->id,
                'title'                => 'ILA — Aug 3',
                'max_score'            => 100,
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'This section would have 4 graded assessments on '.self::ILA_DATE.' — the WAT limit is 3 graded assessments per day.']);

        $this->assertDatabaseHas('class_record_ila_dates', ['id' => $ilaDate->id, 'is_graded' => false]);
    }

    public function test_locked_quarter_blocks_grading(): void
    {
        Carbon::setTestNow(self::BEFORE_DEADLINE);

        $teacher = User::factory()->create();
        $record  = $this->makeRecord($teacher, $this->makeSection(), $this->makeSubject());
        $quarter = $this->makeQuarter($record, locked: true);
        $ilaDate = $this->makeIlaDate($quarter);

        $this->actingAs($teacher)
            ->postJson(route('class-records.ila.grade-date', ['classRecord' => $record->id, 'q' => 1, 'ilaDate' => $ilaDate->id]), [
                'grading_category_id' => $this->category->id,
                'title'                => 'ILA — Aug 3',
                'max_score'            => 100,
            ])
            ->assertStatus(403);
    }

    public function test_non_owner_cannot_grade(): void
    {
        Carbon::setTestNow(self::BEFORE_DEADLINE);

        $owner    = User::factory()->create();
        $stranger = User::factory()->create();
        $record   = $this->makeRecord($owner, $this->makeSection(), $this->makeSubject());
        $quarter  = $this->makeQuarter($record);
        $ilaDate  = $this->makeIlaDate($quarter);

        $this->actingAs($stranger)
            ->postJson(route('class-records.ila.grade-date', ['classRecord' => $record->id, 'q' => 1, 'ilaDate' => $ilaDate->id]), [
                'grading_category_id' => $this->category->id,
                'title'                => 'ILA — Aug 3',
                'max_score'            => 100,
            ])
            ->assertForbidden();
    }

    // ── Un-grading ─────────────────────────────────────────────────────────────

    public function test_teacher_can_ungrade_before_the_deadline(): void
    {
        Carbon::setTestNow(self::BEFORE_DEADLINE);

        $teacher = User::factory()->create();
        $record  = $this->makeRecord($teacher, $this->makeSection(), $this->makeSubject());
        $quarter = $this->makeQuarter($record);
        $ilaDate = $this->makeIlaDate($quarter);

        $this->actingAs($teacher)
            ->postJson(route('class-records.ila.grade-date', ['classRecord' => $record->id, 'q' => 1, 'ilaDate' => $ilaDate->id]), [
                'grading_category_id' => $this->category->id, 'title' => 'ILA — Aug 3', 'max_score' => 100,
            ])->assertOk();

        $assessmentId = ClassRecordIlaDate::find($ilaDate->id)->class_record_assessment_id;

        $this->actingAs($teacher)
            ->deleteJson(route('class-records.ila.ungrade-date', ['classRecord' => $record->id, 'q' => 1, 'ilaDate' => $ilaDate->id]))
            ->assertOk();

        $this->assertDatabaseHas('class_record_ila_dates', [
            'id' => $ilaDate->id, 'is_graded' => false, 'class_record_assessment_id' => null,
        ]);
        $this->assertDatabaseMissing('class_record_assessments', ['id' => $assessmentId]);
        $this->assertDatabaseCount('class_record_scores', 0);
    }

    public function test_ungrade_is_blocked_after_the_deadline(): void
    {
        Carbon::setTestNow(self::BEFORE_DEADLINE);

        $teacher = User::factory()->create();
        $record  = $this->makeRecord($teacher, $this->makeSection(), $this->makeSubject());
        $quarter = $this->makeQuarter($record);
        $ilaDate = $this->makeIlaDate($quarter);

        $this->actingAs($teacher)
            ->postJson(route('class-records.ila.grade-date', ['classRecord' => $record->id, 'q' => 1, 'ilaDate' => $ilaDate->id]), [
                'grading_category_id' => $this->category->id, 'title' => 'ILA — Aug 3', 'max_score' => 100,
            ])->assertOk();

        Carbon::setTestNow(self::AFTER_DEADLINE);

        $this->actingAs($teacher)
            ->deleteJson(route('class-records.ila.ungrade-date', ['classRecord' => $record->id, 'q' => 1, 'ilaDate' => $ilaDate->id]))
            ->assertStatus(422);

        $this->assertDatabaseHas('class_record_ila_dates', ['id' => $ilaDate->id, 'is_graded' => true]);
    }

    // ── WAT visibility ───────────────────────────────────────────────────────────

    public function test_non_graded_ila_date_shows_up_in_wat_but_does_not_count_toward_caps(): void
    {
        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $record  = $this->makeRecord($teacher, $section, $this->makeSubject());
        $quarter = $this->makeQuarter($record);
        $this->makeIlaDate($quarter);

        $wat = WatRuleService::weekData($section->id, $this->sy->id, self::ILA_DATE);

        $monday = collect($wat['days'])->firstWhere('date', self::ILA_DATE);
        $this->assertNotNull($monday);
        $ilaItem = collect($monday['items'])->firstWhere('assessment_type', 'ila');
        $this->assertNotNull($ilaItem, 'Non-graded ILA date should appear in the WAT grid.');
        $this->assertFalse($ilaItem['is_graded']);
        $this->assertSame(0, $monday['graded_count'], 'Non-graded ILA entries must not count toward the daily graded cap.');
        $this->assertSame(0, $wat['totals']['graded']);
    }

    public function test_graded_ila_date_counts_toward_wat_totals(): void
    {
        Carbon::setTestNow(self::BEFORE_DEADLINE);

        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $record  = $this->makeRecord($teacher, $section, $this->makeSubject());
        $quarter = $this->makeQuarter($record);
        $ilaDate = $this->makeIlaDate($quarter);

        $this->actingAs($teacher)
            ->postJson(route('class-records.ila.grade-date', ['classRecord' => $record->id, 'q' => 1, 'ilaDate' => $ilaDate->id]), [
                'grading_category_id' => $this->category->id, 'title' => 'ILA — Aug 3', 'max_score' => 100,
            ])->assertOk();

        $wat = WatRuleService::weekData($section->id, $this->sy->id, self::ILA_DATE);

        $this->assertSame(1, $wat['totals']['graded']);
        $monday = collect($wat['days'])->firstWhere('date', self::ILA_DATE);
        $this->assertSame(1, $monday['graded_count']);
    }

    // ── Rolling-window generation ────────────────────────────────────────────────

    public function test_generate_command_pre_creates_ila_dates_for_the_upcoming_week_by_default(): void
    {
        // 2026-07-27 is a Monday — generating the default 10-day rolling
        // window from "today" must reach the following Monday (2026-08-03),
        // so its graded/non-graded decision can be locked in by this
        // Friday's noon deadline.
        Carbon::setTestNow('2026-07-27 06:00:00');

        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $subject = $this->makeSubject();
        $record  = $this->makeRecord($teacher, $section, $subject);
        $quarter = $this->makeQuarter($record);
        \App\Models\FacultyLoading\ClassSchedule::create([
            'user_id' => $teacher->id, 'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => AcademicTerm::where('is_current', true)->value('id'),
            'entry_type' => 'class', 'session_type' => 'ilp', 'day_of_week' => 'Monday',
            'start_time' => '15:30', 'end_time' => '16:00', 'status' => 'active',
        ]);

        $this->artisan(GenerateIlaDates::class)->assertSuccessful();

        $this->assertDatabaseHas('class_record_ila_dates', [
            'class_record_quarter_id' => $quarter->id, 'date' => '2026-07-27',
        ]);
        $this->assertDatabaseHas('class_record_ila_dates', [
            'class_record_quarter_id' => $quarter->id, 'date' => self::ILA_DATE,
        ]);
    }
}
