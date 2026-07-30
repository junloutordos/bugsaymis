<?php

namespace Tests\Feature\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordTeacher;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use App\Services\ClassRecord\WatRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Individual Faculty WAT Tracker — the teacher -> sections direction
 * (WatRuleService::facultyWeekData() and the my-tracker route). Does NOT
 * touch WAT PDF printing (wat-pdf.blade.php / printForm / renderPdf), which
 * remains covered/untouched by WeeklyAssessmentTrackerTest.php.
 */
class WeeklyAssessmentTrackerMyTrackerTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private GradingOption $option;
    private GradingCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $this->option = GradingOption::create(['name' => 'Default', 'is_active' => true]);
        $this->category = GradingCategory::create([
            'grading_option_id' => $this->option->id, 'name' => 'Formative', 'code' => 'FA',
            'weight' => 1.0, 'max_assessments' => 10, 'sort_order' => 1,
        ]);
    }

    private function makeSection(array $overrides = []): Section
    {
        return Section::create(array_merge([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $this->sy->id,
            'school_year_id' => $this->sy->id, 'is_active' => true,
        ], $overrides));
    }

    private function makeSubject(array $overrides = []): Subject
    {
        static $i = 0;
        $i++;

        return Subject::create(array_merge([
            'school_year_id' => $this->sy->id, 'code' => "SUBJ{$i}", 'name' => "Subject {$i}",
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ], $overrides));
    }

    private function makeClassRecord(Section $section, Subject $subject, User $teacher): ClassRecord
    {
        return ClassRecord::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'grading_option_id' => $this->option->id, 'school_year_id' => $this->sy->id,
            'school_year' => $this->sy->name, 'subject_name' => $subject->name,
            'year_level_section' => "G-{$section->levelid} {$section->sectionname}",
            'teacher_id' => $teacher->id, 'status' => 'draft',
        ]);
    }

    private function makeAssessment(ClassRecord $record, int $number, string $date, bool $isMajor = false): ClassRecordAssessment
    {
        $quarter = ClassRecordQuarter::firstOrCreate(
            ['class_record_id' => $record->id, 'quarter' => 1],
            ['grading_option_id' => $this->option->id],
        );

        return ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $this->category->id,
            'assessment_type' => 'formative', 'is_graded' => true, 'is_major' => $isMajor,
            'assessment_number' => $number, 'title' => "Quiz {$number}", 'activity_date' => $date,
            'plotted_at' => now(), 'max_score' => 20, 'sort_order' => $number,
        ]);
    }

    // ── facultyWeekData() aggregation ─────────────────────────────────────────

    public function test_faculty_week_data_aggregates_across_multiple_sections_and_subjects(): void
    {
        $teacher = User::factory()->create(['name' => 'Juana Dela Cruz']);
        $sectionA = $this->makeSection(['levelid' => 8, 'sectionname' => 'Emerald']);
        $sectionB = $this->makeSection(['levelid' => 9, 'sectionname' => 'Topaz']);
        $subjectA = $this->makeSubject();
        $subjectB = $this->makeSubject();

        $recordA = $this->makeClassRecord($sectionA, $subjectA, $teacher);
        $this->makeAssessment($recordA, 1, '2025-09-01');

        $recordB = $this->makeClassRecord($sectionB, $subjectB, $teacher);
        $this->makeAssessment($recordB, 1, '2025-09-02', true);

        $tracker = WatRuleService::facultyWeekData($teacher->id, $this->sy->id, '2025-09-01');

        $this->assertSame(2, $tracker['totals']['graded']);
        $this->assertSame(1, $tracker['totals']['major']);
        $this->assertCount(2, $tracker['sections']);

        $monday = collect($tracker['days'])->firstWhere('date', '2025-09-01');
        $tuesday = collect($tracker['days'])->firstWhere('date', '2025-09-02');
        $this->assertCount(1, $monday['items']);
        $this->assertCount(1, $tuesday['items']);
        $this->assertTrue($tuesday['items'][0]['is_major']);
    }

    public function test_faculty_week_data_excludes_a_different_teachers_assessments(): void
    {
        $teacher = User::factory()->create(['name' => 'Juana Dela Cruz']);
        $otherTeacher = User::factory()->create(['name' => 'Pedro Penduko']);
        $section = $this->makeSection();
        $subject = $this->makeSubject();

        $otherRecord = $this->makeClassRecord($section, $subject, $otherTeacher);
        $this->makeAssessment($otherRecord, 1, '2025-09-01');

        $tracker = WatRuleService::facultyWeekData($teacher->id, $this->sy->id, '2025-09-01');

        $this->assertSame(0, $tracker['totals']['graded']);
        $this->assertCount(0, $tracker['sections']);
    }

    public function test_faculty_week_data_includes_pehm_co_teacher_records(): void
    {
        $primaryTeacher = User::factory()->create(['name' => 'Primary Teacher']);
        $coTeacher = User::factory()->create(['name' => 'Co Teacher']);
        $section = $this->makeSection();
        $subject = $this->makeSubject();

        $record = $this->makeClassRecord($section, $subject, $primaryTeacher);
        ClassRecordTeacher::create([
            'class_record_id' => $record->id, 'subject_id' => $subject->id,
            'user_id' => $coTeacher->id, 'is_primary' => false,
        ]);
        $this->makeAssessment($record, 1, '2025-09-01');

        // The assessment's teacher_name is resolved from cr.teacher_id in
        // WatRuleService::weekData() — a co-teacher's own tracker won't
        // show items whose class_record_assessments row is attributed to
        // the primary teacher's name. What matters here is that the SHARED
        // record surfaces in the co-teacher's section list at all (so they
        // can see the section's remaining room), even with zero of their
        // own plotted items.
        $tracker = WatRuleService::facultyWeekData($coTeacher->id, $this->sy->id, '2025-09-01');

        $this->assertCount(1, $tracker['sections']);
        $this->assertSame($section->id, $tracker['sections'][0]['section_id']);
    }

    public function test_faculty_week_data_reports_remaining_room_shared_with_co_teachers(): void
    {
        $teacherA = User::factory()->create(['name' => 'Teacher A']);
        $teacherB = User::factory()->create(['name' => 'Teacher B']);
        $section = $this->makeSection();
        $subjectA = $this->makeSubject();
        $subjectB = $this->makeSubject();

        $recordA = $this->makeClassRecord($section, $subjectA, $teacherA);
        $this->makeAssessment($recordA, 1, '2025-09-01');
        $this->makeAssessment($recordA, 2, '2025-09-01');

        $recordB = $this->makeClassRecord($section, $subjectB, $teacherB);
        $this->makeAssessment($recordB, 1, '2025-09-01');

        // Section-wide: 3 graded plotted this week (2 by Teacher A, 1 by
        // Teacher B) — WEEKLY_GRADED_MAX is 15, so 12 remain, and that
        // remaining count is identical from either teacher's own tracker
        // since it reflects the SECTION's shared budget, not a private one.
        $trackerA = WatRuleService::facultyWeekData($teacherA->id, $this->sy->id, '2025-09-01');
        $trackerB = WatRuleService::facultyWeekData($teacherB->id, $this->sy->id, '2025-09-01');

        $this->assertSame(12, $trackerA['sections'][0]['remaining']['weekly_graded']);
        $this->assertSame(12, $trackerB['sections'][0]['remaining']['weekly_graded']);
        $this->assertSame(2, $trackerA['sections'][0]['graded_count']);
        $this->assertSame(1, $trackerB['sections'][0]['graded_count']);
    }

    public function test_faculty_week_data_excludes_archived_records(): void
    {
        $teacher = User::factory()->create(['name' => 'Juana Dela Cruz']);
        $section = $this->makeSection();
        $subject = $this->makeSubject();

        $record = $this->makeClassRecord($section, $subject, $teacher);
        $this->makeAssessment($record, 1, '2025-09-01');
        $record->update(['status' => 'archived']);

        $tracker = WatRuleService::facultyWeekData($teacher->id, $this->sy->id, '2025-09-01');

        $this->assertCount(0, $tracker['sections']);
    }

    // ── GET /class-records/wat/my-tracker ─────────────────────────────────────

    public function test_any_teacher_with_a_class_record_can_view_their_own_tracker(): void
    {
        $teacher = User::factory()->create(['name' => 'Juana Dela Cruz']);
        $section = $this->makeSection();
        $subject = $this->makeSubject();
        $record = $this->makeClassRecord($section, $subject, $teacher);
        $this->makeAssessment($record, 1, '2025-09-01');

        $this->actingAs($teacher)
            ->get(route('class-records.wat.my-tracker', ['week' => '2025-09-01']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('tracker')
                ->where('tracker.totals.graded', 1));
    }

    public function test_teacher_with_no_class_records_sees_a_null_tracker_not_a_403(): void
    {
        $teacher = User::factory()->create();

        $this->actingAs($teacher)
            ->get(route('class-records.wat.my-tracker'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('tracker', null));
    }

    public function test_my_tracker_only_shows_the_authenticated_users_own_data(): void
    {
        $teacher = User::factory()->create(['name' => 'Juana Dela Cruz']);
        $otherTeacher = User::factory()->create(['name' => 'Pedro Penduko']);
        $section = $this->makeSection();
        $subject = $this->makeSubject();

        $ownRecord = $this->makeClassRecord($section, $subject, $teacher);
        $this->makeAssessment($ownRecord, 1, '2025-09-01');

        $otherSection = $this->makeSection(['sectionname' => 'Ruby']);
        $otherRecord = $this->makeClassRecord($otherSection, $subject, $otherTeacher);
        $this->makeAssessment($otherRecord, 1, '2025-09-01');

        $this->actingAs($teacher)
            ->get(route('class-records.wat.my-tracker', ['week' => '2025-09-01']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('tracker.sections', 1)
                ->where('tracker.sections.0.section_id', $section->id));
    }
}
