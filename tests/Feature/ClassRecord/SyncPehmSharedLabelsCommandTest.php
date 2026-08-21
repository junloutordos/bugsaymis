<?php

namespace Tests\Feature\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordTeacher;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One-off backfill: class-record:consolidate-pehm-coteaching (the 08-19
 * data-consolidation command) attached co-teacher pivot rows directly and
 * never called ClassRecord::syncSharedDisplayLabel() — so records it merged
 * kept their original single-subject subject_name (e.g. "Health 2") even
 * though they now have 3 co-teachers. This command re-derives subject_name
 * on every such record.
 */
class SyncPehmSharedLabelsCommandTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private GradingOption $option;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31',
            'is_current' => true, 'status' => 'active',
        ]);
        $this->option = GradingOption::create(['name' => 'PEHM Grading', 'is_active' => true]);
    }

    private function makeSubject(string $name, string $code, int $gradeLevel): Subject
    {
        return Subject::create([
            'school_year_id' => $this->sy->id, 'code' => $code, 'name' => $name,
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => $gradeLevel, 'subject_group' => 'PEHM', 'sessions_per_week' => 2,
            'minutes_per_session' => 60, 'is_active' => true,
        ]);
    }

    private function makeRecord(User $teacher, Subject $subject, string $subjectName): ClassRecord
    {
        return ClassRecord::create([
            'subject_id' => $subject->id, 'section_id' => 1,
            'grading_option_id' => $this->option->id,
            'school_year_id' => $this->sy->id, 'school_year' => $this->sy->name,
            'subject_name' => $subjectName,
            'year_level_section' => "G-{$subject->grade_level} Emerald",
            'teacher_id' => $teacher->id, 'status' => 'draft',
        ]);
    }

    public function test_dry_run_reports_a_stale_labeled_shared_record_without_writing(): void
    {
        $peSubject = $this->makeSubject('Physical Education 2', 'PE2', 8);
        $healthSubject = $this->makeSubject('Health 2', 'HEA2', 8);
        $musicSubject = $this->makeSubject('Music 2', 'MUS2', 8);
        $peTeacher = User::factory()->create();
        $healthTeacher = User::factory()->create();
        $musicTeacher = User::factory()->create();

        // Simulates what consolidate-pehm-coteaching left behind: pivot rows
        // for every teacher, but subject_name still the creator's own subject.
        $record = $this->makeRecord($peTeacher, $peSubject, 'Health 2');
        ClassRecordTeacher::create(['class_record_id' => $record->id, 'subject_id' => $peSubject->id, 'user_id' => $peTeacher->id, 'is_primary' => true]);
        ClassRecordTeacher::create(['class_record_id' => $record->id, 'subject_id' => $healthSubject->id, 'user_id' => $healthTeacher->id]);
        ClassRecordTeacher::create(['class_record_id' => $record->id, 'subject_id' => $musicSubject->id, 'user_id' => $musicTeacher->id]);

        $this->artisan('class-record:sync-pehm-shared-labels')
            ->expectsOutputToContain('cr'.$record->id.': "Health 2" -> "PEHM 2"')
            ->assertExitCode(0);

        $this->assertSame('Health 2', $record->fresh()->subject_name);
    }

    public function test_commit_writes_the_corrected_label(): void
    {
        $peSubject = $this->makeSubject('Physical Education 2', 'PE2', 8);
        $healthSubject = $this->makeSubject('Health 2', 'HEA2', 8);
        $musicSubject = $this->makeSubject('Music 2', 'MUS2', 8);
        $peTeacher = User::factory()->create();
        $healthTeacher = User::factory()->create();
        $musicTeacher = User::factory()->create();

        $record = $this->makeRecord($peTeacher, $peSubject, 'Health 2');
        ClassRecordTeacher::create(['class_record_id' => $record->id, 'subject_id' => $peSubject->id, 'user_id' => $peTeacher->id, 'is_primary' => true]);
        ClassRecordTeacher::create(['class_record_id' => $record->id, 'subject_id' => $healthSubject->id, 'user_id' => $healthTeacher->id]);
        ClassRecordTeacher::create(['class_record_id' => $record->id, 'subject_id' => $musicSubject->id, 'user_id' => $musicTeacher->id]);

        $this->artisan('class-record:sync-pehm-shared-labels', ['--commit' => true])
            ->assertExitCode(0);

        $this->assertSame('PEHM 2', $record->fresh()->subject_name);
    }

    public function test_records_with_fewer_than_two_co_teachers_are_left_alone(): void
    {
        $peSubject = $this->makeSubject('Physical Education 1', 'PE1', 7);
        $peTeacher = User::factory()->create();

        $record = $this->makeRecord($peTeacher, $peSubject, 'Physical Education 1');
        ClassRecordTeacher::create(['class_record_id' => $record->id, 'subject_id' => $peSubject->id, 'user_id' => $peTeacher->id, 'is_primary' => true]);

        $this->artisan('class-record:sync-pehm-shared-labels', ['--commit' => true])
            ->assertExitCode(0);

        $this->assertSame('Physical Education 1', $record->fresh()->subject_name);
    }

    public function test_already_correctly_labeled_shared_record_is_reported_as_unchanged(): void
    {
        $peSubject = $this->makeSubject('Physical Education 1', 'PE1', 7);
        $healthSubject = $this->makeSubject('Health 1', 'HEA1', 7);
        $peTeacher = User::factory()->create();
        $healthTeacher = User::factory()->create();

        $record = $this->makeRecord($peTeacher, $peSubject, 'PEHM 1');
        ClassRecordTeacher::create(['class_record_id' => $record->id, 'subject_id' => $peSubject->id, 'user_id' => $peTeacher->id, 'is_primary' => true]);
        ClassRecordTeacher::create(['class_record_id' => $record->id, 'subject_id' => $healthSubject->id, 'user_id' => $healthTeacher->id]);

        $this->artisan('class-record:sync-pehm-shared-labels')
            ->expectsOutputToContain('Totals: 0 label(s) would change, 1 already correct.')
            ->assertExitCode(0);
    }

    public function test_archived_records_are_excluded(): void
    {
        $peSubject = $this->makeSubject('Physical Education 2', 'PE2', 8);
        $healthSubject = $this->makeSubject('Health 2', 'HEA2', 8);
        $peTeacher = User::factory()->create();
        $healthTeacher = User::factory()->create();

        $record = $this->makeRecord($peTeacher, $peSubject, 'Health 2');
        $record->update(['status' => 'archived']);
        ClassRecordTeacher::create(['class_record_id' => $record->id, 'subject_id' => $peSubject->id, 'user_id' => $peTeacher->id, 'is_primary' => true]);
        ClassRecordTeacher::create(['class_record_id' => $record->id, 'subject_id' => $healthSubject->id, 'user_id' => $healthTeacher->id]);

        $this->artisan('class-record:sync-pehm-shared-labels', ['--commit' => true])
            ->assertExitCode(0);

        $this->assertSame('Health 2', $record->fresh()->subject_name);
    }
}
