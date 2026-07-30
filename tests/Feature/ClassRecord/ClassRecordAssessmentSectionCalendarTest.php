<?php

namespace Tests\Feature\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for GET /class-records/{cr}/section-calendar.
 *
 * This endpoint backs Show.vue's client-side date pre-check (onDateChange())
 * — it must report the exact same "already plotted" counts the backend WAT
 * cap check (ClassRecordAssessmentController::upsert(), via
 * ClassRecordAssessment::schoolYearScopeQuery()) uses, or the UI blocks a
 * date the server would actually accept.
 *
 * Production symptom: an archived class record's leftover assessments on a
 * date were counted here (a hand-rolled query that predated and bypassed
 * schoolYearScopeQuery()'s archived exclusion), inflating the client-side
 * count above the daily cap and blocking a legitimately available date —
 * even though the real, authoritative server count was under the limit.
 */
class ClassRecordAssessmentSectionCalendarTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private GradingOption $option;
    private GradingCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-06-30',
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
            'levelid' => 10, 'sectionname' => 'Electron', 'syid' => $this->sy->id,
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
            'grade_level' => 10, 'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ], $overrides));
    }

    private function makeRecord(Section $section, Subject $subject, User $teacher, string $status = 'draft'): ClassRecord
    {
        return ClassRecord::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'grading_option_id' => $this->option->id, 'school_year_id' => $this->sy->id,
            'school_year' => $this->sy->name, 'subject_name' => $subject->name,
            'year_level_section' => "G-{$section->levelid} {$section->sectionname}",
            'teacher_id' => $teacher->id, 'status' => $status,
        ]);
    }

    private function makeAssessment(ClassRecord $record, int $number, string $date): ClassRecordAssessment
    {
        $quarter = ClassRecordQuarter::firstOrCreate(
            ['class_record_id' => $record->id, 'quarter' => 1],
            ['grading_option_id' => $this->option->id],
        );

        return ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $this->category->id,
            'assessment_type' => 'formative', 'is_graded' => true, 'is_major' => false,
            'assessment_number' => $number, 'title' => "Quiz {$number}", 'activity_date' => $date,
            'plotted_at' => now(), 'max_score' => 20, 'sort_order' => $number,
        ]);
    }

    public function test_section_calendar_excludes_an_archived_records_assessment(): void
    {
        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $subject = $this->makeSubject();

        // Two archived records with their own plotted assessments on the
        // same day, plus one active record's assessment — the exact
        // production scenario (Grade 10 Electron, July 31: 2 archived rows
        // + 1 active row inflated the client-side count to 3, blocking a
        // date the server's real count of 1 would have allowed).
        $archived1 = $this->makeRecord($section, $subject, $teacher, 'archived');
        $this->makeAssessment($archived1, 1, '2026-07-31');
        $archived2 = $this->makeRecord($section, $subject, $teacher, 'archived');
        $this->makeAssessment($archived2, 1, '2026-07-31');

        $activeRecord = $this->makeRecord($section, $subject, $teacher);
        $this->makeAssessment($activeRecord, 1, '2026-07-31');

        $response = $this->actingAs($teacher)
            ->getJson(route('class-records.assessments.section-calendar', ['classRecord' => $activeRecord->id]))
            ->assertOk();

        $day = collect($response->json())->firstWhere('date', '2026-07-31');

        $this->assertNotNull($day);
        $this->assertSame(1, $day['graded_count']);
        $this->assertCount(1, $day['items']);
    }

    public function test_section_calendar_pools_a_science_core_synthetic_section(): void
    {
        $teacher = User::factory()->create();
        $homeroom = $this->makeSection(['levelid' => 10, 'sectionname' => 'Electron']);
        $scienceCore = $this->makeSection(['levelid' => 10, 'sectionname' => 'SCI-PHY3L2-G10-1']);
        $subject = $this->makeSubject(['grade_level' => 10]);

        $pooledRecord = $this->makeRecord($scienceCore, $subject, $teacher);
        $this->makeAssessment($pooledRecord, 1, '2026-07-31');

        $activeRecord = $this->makeRecord($homeroom, $subject, $teacher);

        $response = $this->actingAs($teacher)
            ->getJson(route('class-records.assessments.section-calendar', ['classRecord' => $activeRecord->id]))
            ->assertOk();

        $day = collect($response->json())->firstWhere('date', '2026-07-31');

        $this->assertNotNull($day);
        $this->assertSame(1, $day['graded_count']);
    }

    public function test_section_calendar_does_not_pool_a_sibling_homeroom(): void
    {
        $teacher = User::factory()->create();
        $opal = $this->makeSection(['levelid' => 7, 'sectionname' => 'Opal']);
        $sapphire = $this->makeSection(['levelid' => 7, 'sectionname' => 'Sapphire']);
        $subject = $this->makeSubject(['grade_level' => 7]);

        $sapphireRecord = $this->makeRecord($sapphire, $subject, $teacher);
        $this->makeAssessment($sapphireRecord, 1, '2026-07-31');

        $opalRecord = $this->makeRecord($opal, $subject, $teacher);

        $response = $this->actingAs($teacher)
            ->getJson(route('class-records.assessments.section-calendar', ['classRecord' => $opalRecord->id]))
            ->assertOk();

        $day = collect($response->json())->firstWhere('date', '2026-07-31');

        $this->assertNull($day);
    }
}
