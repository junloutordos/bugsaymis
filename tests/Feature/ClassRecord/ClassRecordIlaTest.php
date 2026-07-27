<?php

namespace Tests\Feature\ClassRecord;

use App\Console\Commands\ClassRecord\GenerateIlaDates;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordIlaDate;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassRecordIlaTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private AcademicTerm $term;
    private GradingOption $option;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $this->term = AcademicTerm::create([
            'school_year_id' => $this->sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2026-01-15', 'is_current' => true,
        ]);
        $this->option = GradingOption::create(['name' => 'Default', 'is_active' => true]);
    }

    private function makeSection(): Section
    {
        return Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $this->sy->id,
            'school_year_id' => $this->sy->id, 'is_active' => true,
        ]);
    }

    private function makeSubject(bool $hasIlp = true): Subject
    {
        return Subject::create([
            'school_year_id' => $this->sy->id, 'code' => 'SUBJ1', 'name' => 'Subject 1',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
            'has_ilp' => $hasIlp,
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

    private function makeQuarter(ClassRecord $record, int $q = 1, bool $locked = false): ClassRecordQuarter
    {
        return ClassRecordQuarter::create([
            'class_record_id' => $record->id, 'grading_option_id' => $this->option->id,
            'quarter' => $q, 'is_locked' => $locked,
        ]);
    }

    private function makeIlpSchedule(User $teacher, Section $section, Subject $subject, string $day): ClassSchedule
    {
        return ClassSchedule::create([
            'user_id' => $teacher->id, 'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
            'entry_type' => 'class', 'session_type' => 'ilp', 'day_of_week' => $day,
            'start_time' => '15:30', 'end_time' => '16:00', 'status' => 'active',
        ]);
    }

    // ── GenerateIlaDates command ───────────────────────────────────────────────

    public function test_command_creates_an_auto_generated_ila_date_for_a_matching_schedule(): void
    {
        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $subject = $this->makeSubject();
        $record = $this->makeRecord($teacher, $section, $subject);
        $quarter = $this->makeQuarter($record);
        $this->makeIlpSchedule($teacher, $section, $subject, 'Monday');

        $this->artisan(GenerateIlaDates::class, ['--date' => '2026-08-03']) // a Monday
            ->assertSuccessful();

        $this->assertDatabaseHas('class_record_ila_dates', [
            'class_record_quarter_id' => $quarter->id,
            'date' => '2026-08-03',
            'is_auto_generated' => true,
        ]);
    }

    public function test_command_skips_subjects_without_has_ilp(): void
    {
        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $subject = $this->makeSubject(hasIlp: false);
        $record = $this->makeRecord($teacher, $section, $subject);
        $this->makeQuarter($record);
        $this->makeIlpSchedule($teacher, $section, $subject, 'Monday');

        $this->artisan(GenerateIlaDates::class, ['--date' => '2026-08-03'])->assertSuccessful();

        $this->assertDatabaseCount('class_record_ila_dates', 0);
    }

    public function test_command_skips_when_no_class_record_exists_yet(): void
    {
        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $subject = $this->makeSubject();
        $this->makeIlpSchedule($teacher, $section, $subject, 'Monday');

        $this->artisan(GenerateIlaDates::class, ['--date' => '2026-08-03'])->assertSuccessful();

        $this->assertDatabaseCount('class_record_ila_dates', 0);
    }

    public function test_command_skips_when_quarter_is_ambiguous(): void
    {
        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $subject = $this->makeSubject();
        $record = $this->makeRecord($teacher, $section, $subject);
        $this->makeQuarter($record, 1, locked: false);
        $this->makeQuarter($record, 2, locked: false); // two unlocked quarters — ambiguous
        $this->makeIlpSchedule($teacher, $section, $subject, 'Monday');

        $this->artisan(GenerateIlaDates::class, ['--date' => '2026-08-03'])->assertSuccessful();

        $this->assertDatabaseCount('class_record_ila_dates', 0);
    }

    public function test_command_skips_archived_class_records(): void
    {
        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $subject = $this->makeSubject();
        $record = $this->makeRecord($teacher, $section, $subject);
        $record->update(['status' => 'archived']);
        $this->makeQuarter($record);
        $this->makeIlpSchedule($teacher, $section, $subject, 'Monday');

        $this->artisan(GenerateIlaDates::class, ['--date' => '2026-08-03'])->assertSuccessful();

        $this->assertDatabaseCount('class_record_ila_dates', 0);
    }

    public function test_command_does_not_fire_on_a_non_scheduled_day(): void
    {
        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $subject = $this->makeSubject();
        $record = $this->makeRecord($teacher, $section, $subject);
        $this->makeQuarter($record);
        $this->makeIlpSchedule($teacher, $section, $subject, 'Friday');

        $this->artisan(GenerateIlaDates::class, ['--date' => '2026-08-03']) // a Monday
            ->assertSuccessful();

        $this->assertDatabaseCount('class_record_ila_dates', 0);
    }

    // ── Controller endpoints ───────────────────────────────────────────────────

    public function test_teacher_can_mark_and_save_ila_status(): void
    {
        $teacher = User::factory()->create();
        $record = $this->makeRecord($teacher, $this->makeSection(), $this->makeSubject());
        $quarter = $this->makeQuarter($record);
        $date = ClassRecordIlaDate::create(['class_record_quarter_id' => $quarter->id, 'date' => '2026-08-03', 'sort_order' => 1]);
        $student = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'Doe', 'given_name' => 'Jane',
            'sequence_number' => 1, 'is_active' => true,
        ]);

        $this->actingAs($teacher)
            ->postJson(route('class-records.ila.upsert', ['classRecord' => $record->id, 'q' => 1]), [
                'records' => [['date_id' => $date->id, 'student_id' => $student->id, 'status' => 'compliant']],
            ])
            ->assertOk();

        $this->assertDatabaseHas('class_record_ila_records', [
            'class_record_ila_date_id' => $date->id,
            'class_record_student_id' => $student->id,
            'status' => 'compliant',
        ]);
    }

    public function test_locked_quarter_blocks_ila_changes(): void
    {
        $teacher = User::factory()->create();
        $record = $this->makeRecord($teacher, $this->makeSection(), $this->makeSubject());
        $quarter = $this->makeQuarter($record, 1, locked: true);

        $this->actingAs($teacher)
            ->postJson(route('class-records.ila.store-date', ['classRecord' => $record->id, 'q' => 1]), [
                'date' => '2026-08-03',
            ])
            ->assertStatus(403);
    }

    public function test_non_owner_cannot_view_ila(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $record = $this->makeRecord($owner, $this->makeSection(), $this->makeSubject());
        $this->makeQuarter($record);

        $this->actingAs($stranger)
            ->getJson(route('class-records.ila.index', ['classRecord' => $record->id, 'q' => 1]))
            ->assertForbidden();
    }
}
