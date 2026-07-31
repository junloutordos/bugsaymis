<?php

namespace Tests\Feature\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordScore;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the Setup tab's assessment "X" delete button.
 *
 * Before this fix, ClassRecordAssessmentController::upsert() only ever
 * inserted/updated rows present in the payload — a removed row silently
 * survived in the database and reappeared on the very next reload. Fixing
 * persistence also required switching identity matching from
 * (category, number) position to the stable row id, since position shifts
 * on every removal and would otherwise silently reassign one row's scores
 * to whatever content lands on its old slot.
 */
class ClassRecordAssessmentControllerTest extends TestCase
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

    private function makeSection(): Section
    {
        return Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $this->sy->id,
            'school_year_id' => $this->sy->id, 'is_active' => true,
        ]);
    }

    private function makeSubject(): Subject
    {
        return Subject::create([
            'school_year_id' => $this->sy->id, 'code' => 'SUBJ1', 'name' => 'Subject 1',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
    }

    private function makeRecordAndQuarter(User $teacher, Section $section, Subject $subject): ClassRecordQuarter
    {
        $record = ClassRecord::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'grading_option_id' => $this->option->id, 'school_year_id' => $this->sy->id,
            'school_year' => $this->sy->name, 'subject_name' => $subject->name,
            'year_level_section' => "G-{$section->levelid} {$section->sectionname}",
            'teacher_id' => $teacher->id, 'status' => 'draft',
        ]);

        return ClassRecordQuarter::create([
            'class_record_id' => $record->id, 'grading_option_id' => $this->option->id, 'quarter' => 1,
        ]);
    }

    private function makeAssessment(ClassRecordQuarter $quarter, int $number, string $title): ClassRecordAssessment
    {
        return ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $this->category->id,
            'assessment_number' => $number, 'title' => $title, 'assessment_type' => 'formative',
            'is_graded' => true, 'is_major' => false, 'max_score' => 20, 'sort_order' => $number,
        ]);
    }

    private function payloadFor(ClassRecordAssessment $a): array
    {
        return [
            'id' => $a->id, 'grading_category_id' => $a->grading_category_id,
            'assessment_number' => $a->assessment_number, 'title' => $a->title,
            'is_graded' => true, 'activity_date' => $a->activity_date?->toDateString() ?? now()->addMonth()->toDateString(), 'max_score' => $a->max_score,
        ];
    }

    public function test_activity_date_is_required(): void
    {
        $teacher = User::factory()->create();
        $quarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $this->makeSubject());
        $assessment = $this->makeAssessment($quarter, 1, 'Quiz 1');

        $payload = $this->payloadFor($assessment);
        $payload['activity_date'] = null;

        $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $quarter->class_record_id, 'q' => 1]), [
                'assessments' => [$payload],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['assessments.0.activity_date']);
    }

    public function test_removing_an_assessment_from_the_payload_deletes_it(): void
    {
        $teacher = User::factory()->create();
        $quarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $this->makeSubject());
        $keep = $this->makeAssessment($quarter, 1, 'Quiz 1');
        $remove = $this->makeAssessment($quarter, 2, 'Quiz 2');

        $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $quarter->class_record_id, 'q' => 1]), [
                'assessments' => [$this->payloadFor($keep)],
            ])
            ->assertOk();

        $this->assertDatabaseHas('class_record_assessments', ['id' => $keep->id]);
        $this->assertDatabaseMissing('class_record_assessments', ['id' => $remove->id]);
    }

    public function test_removing_an_assessment_with_scores_is_blocked_and_nothing_is_deleted(): void
    {
        $teacher = User::factory()->create();
        $quarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $this->makeSubject());
        $keep = $this->makeAssessment($quarter, 1, 'Quiz 1');
        $remove = $this->makeAssessment($quarter, 2, 'Quiz 2');

        $student = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'Dela Cruz', 'given_name' => 'Juan',
            'sex' => 'M', 'sequence_number' => 1, 'is_active' => true,
        ]);
        ClassRecordScore::create([
            'class_record_student_id' => $student->id, 'class_record_assessment_id' => $remove->id, 'score' => 18,
        ]);

        $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $quarter->class_record_id, 'q' => 1]), [
                'assessments' => [$this->payloadFor($keep)],
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('class_record_assessments', ['id' => $keep->id]);
        $this->assertDatabaseHas('class_record_assessments', ['id' => $remove->id]);
    }

    public function test_deleting_a_middle_row_and_renumbering_does_not_corrupt_a_surviving_rows_content_or_scores(): void
    {
        $teacher = User::factory()->create();
        $quarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $this->makeSubject());
        $a = $this->makeAssessment($quarter, 1, 'Quiz 1');
        $b = $this->makeAssessment($quarter, 2, 'Quiz 2'); // will be removed — no scores
        $c = $this->makeAssessment($quarter, 3, 'Quiz 3'); // has scores, renumbers down to slot 2

        $student = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'Dela Cruz', 'given_name' => 'Juan',
            'sex' => 'M', 'sequence_number' => 1, 'is_active' => true,
        ]);
        ClassRecordScore::create([
            'class_record_student_id' => $student->id, 'class_record_assessment_id' => $c->id, 'score' => 15,
        ]);

        // Simulate the frontend: B removed, C renumbered from 3 down to 2 — but C's id is preserved.
        $cPayload = $this->payloadFor($c);
        $cPayload['assessment_number'] = 2;

        $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $quarter->class_record_id, 'q' => 1]), [
                'assessments' => [$this->payloadFor($a), $cPayload],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('class_record_assessments', ['id' => $b->id]);

        $c->refresh();
        $this->assertSame(2, $c->assessment_number);
        $this->assertSame('Quiz 3', $c->title, 'The row that had scores must keep its own title, not adopt slot 2\'s old content.');

        // The score must still point at C's original assessment id.
        $this->assertDatabaseHas('class_record_scores', [
            'class_record_assessment_id' => $c->id,
            'class_record_student_id'    => $student->id,
        ]);
    }

    // ── Apply this setup to other sections (push direction) ────────────────

    public function test_apply_to_sections_copies_assessments_with_dates_to_a_valid_target(): void
    {
        $teacher = User::factory()->create();
        $subject = $this->makeSubject();
        $sourceQuarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $subject);
        $assessment = $this->makeAssessment($sourceQuarter, 1, 'Quiz 1');
        $assessment->update(['activity_date' => '2026-09-07']);

        $targetQuarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $subject);

        $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.apply-to-sections', [
                'classRecord' => $sourceQuarter->class_record_id, 'q' => 1,
            ]), ['target_class_record_ids' => [$targetQuarter->class_record_id]])
            ->assertOk()
            ->assertJsonCount(1, 'applied')
            ->assertJsonCount(0, 'skipped');

        $this->assertDatabaseHas('class_record_assessments', [
            'class_record_quarter_id' => $targetQuarter->id,
            'title'                   => 'Quiz 1',
            'activity_date'           => '2026-09-07',
        ]);
    }

    public function test_apply_to_sections_skips_target_with_mismatched_grading_option(): void
    {
        $teacher = User::factory()->create();
        $subject = $this->makeSubject();
        $sourceQuarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $subject);
        $assessment = $this->makeAssessment($sourceQuarter, 1, 'Quiz 1');
        $assessment->update(['activity_date' => '2026-09-07']);

        $otherOption = GradingOption::create(['name' => 'Other', 'is_active' => true]);
        $targetQuarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $subject);
        $targetQuarter->update(['grading_option_id' => $otherOption->id]);

        $response = $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.apply-to-sections', [
                'classRecord' => $sourceQuarter->class_record_id, 'q' => 1,
            ]), ['target_class_record_ids' => [$targetQuarter->class_record_id]])
            ->assertOk()
            ->assertJsonCount(0, 'applied')
            ->assertJsonCount(1, 'skipped');

        $this->assertStringContainsString('grading option', $response->json('skipped.0.reason'));
    }

    public function test_apply_to_sections_skips_target_that_already_has_assessments(): void
    {
        $teacher = User::factory()->create();
        $subject = $this->makeSubject();
        $sourceQuarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $subject);
        $assessment = $this->makeAssessment($sourceQuarter, 1, 'Quiz 1');
        $assessment->update(['activity_date' => '2026-09-07']);

        $targetQuarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $subject);
        $this->makeAssessment($targetQuarter, 1, 'Existing');

        $response = $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.apply-to-sections', [
                'classRecord' => $sourceQuarter->class_record_id, 'q' => 1,
            ]), ['target_class_record_ids' => [$targetQuarter->class_record_id]])
            ->assertOk()
            ->assertJsonCount(0, 'applied')
            ->assertJsonCount(1, 'skipped');

        $this->assertSame('Already has assessments this quarter.', $response->json('skipped.0.reason'));
    }

    // ── Resubmitting unchanged rows must not double-count toward the cap ─────
    // Regression: every Setup tab save resubmits the WHOLE quarter, including
    // rows nobody touched. The cap check used to exclude those rows from the
    // "existing" baseline (correctly) but then add them straight back in via
    // their own group, landing right back at the full (possibly already
    // over-cap, from OTHER subjects/sections pooling into the same grade)
    // total — incorrectly blocking the entire save even when the change has
    // nothing to do with that date.

    public function test_saving_an_unrelated_new_row_succeeds_even_when_an_unchanged_existing_date_is_already_over_the_pooled_cap(): void
    {
        $teacher = User::factory()->create();
        $subject = $this->makeSubject();
        $section = $this->makeSection();

        // Fill the grade's pooled daily cap for 2026-09-07 entirely from an
        // UNRELATED subject/record — same section, different subject, same
        // day. This is the "other subjects already maxed out this day"
        // scenario reported in production (English 6 / Del Mundo, Jul 30).
        $otherSubject = Subject::create([
            'school_year_id' => $this->sy->id, 'code' => 'SUBJ2', 'name' => 'Subject 2',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $busyQuarter = $this->makeRecordAndQuarter($teacher, $section, $otherSubject);
        for ($i = 1; $i <= 3; $i++) {
            $a = $this->makeAssessment($busyQuarter, $i, "Other Subject {$i}");
            $a->update(['activity_date' => '2026-09-07']);
        }

        // This class record already has ONE assessment saved on that same
        // now-over-cap day (its own, legitimately saved before the day
        // became over-capacity) plus is now adding a brand-new row on a
        // DIFFERENT, uncrowded day.
        $quarter = $this->makeRecordAndQuarter($teacher, $section, $subject);
        $existing = $this->makeAssessment($quarter, 1, 'Existing Quiz');
        $existing->update(['activity_date' => '2026-09-07']);

        $response = $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $quarter->class_record_id, 'q' => 1]), [
                'assessments' => [
                    // Resubmitted UNCHANGED — same id, same date, same everything.
                    $this->payloadFor($existing),
                    // Genuinely new row, unrelated date.
                    [
                        'grading_category_id' => $this->category->id,
                        'assessment_number'   => 2,
                        'title'                => 'New Quiz',
                        'is_graded'            => true,
                        'activity_date'        => '2026-09-08',
                        'max_score'            => 20,
                    ],
                ],
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('class_record_assessments', [
            'class_record_quarter_id' => $quarter->id, 'title' => 'New Quiz', 'activity_date' => '2026-09-08',
        ]);
        // The unchanged row on the over-cap day is untouched, not duplicated
        // or altered — its date/title/id all remain exactly as before.
        $this->assertDatabaseHas('class_record_assessments', [
            'id' => $existing->id, 'activity_date' => '2026-09-07', 'title' => 'Existing Quiz',
        ]);
    }

    public function test_saving_a_genuinely_new_row_on_an_already_over_cap_day_is_still_blocked(): void
    {
        $teacher = User::factory()->create();
        $subject = $this->makeSubject();
        $section = $this->makeSection();

        // Fill the grade's pooled daily cap for 2026-09-07 from another subject.
        $otherSubject = Subject::create([
            'school_year_id' => $this->sy->id, 'code' => 'SUBJ2', 'name' => 'Subject 2',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $busyQuarter = $this->makeRecordAndQuarter($teacher, $section, $otherSubject);
        for ($i = 1; $i <= 3; $i++) {
            $a = $this->makeAssessment($busyQuarter, $i, "Other Subject {$i}");
            $a->update(['activity_date' => '2026-09-07']);
        }

        $quarter = $this->makeRecordAndQuarter($teacher, $section, $subject);

        // A genuinely NEW row (no id) dated on the already-full day must
        // still be rejected — real enforcement is preserved, only the
        // "unchanged row resubmission" false positive is fixed.
        $response = $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $quarter->class_record_id, 'q' => 1]), [
                'assessments' => [[
                    'grading_category_id' => $this->category->id,
                    'assessment_number'   => 1,
                    'title'                => 'New Quiz',
                    'is_graded'            => true,
                    'activity_date'        => '2026-09-07',
                    'max_score'            => 20,
                ]],
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('WAT limit', $response->json('message'));
        $this->assertDatabaseMissing('class_record_assessments', [
            'class_record_quarter_id' => $quarter->id, 'title' => 'New Quiz',
        ]);
    }

    public function test_re_dating_an_existing_row_onto_an_already_over_cap_day_is_still_blocked(): void
    {
        $teacher = User::factory()->create();
        $subject = $this->makeSubject();
        $section = $this->makeSection();

        $otherSubject = Subject::create([
            'school_year_id' => $this->sy->id, 'code' => 'SUBJ2', 'name' => 'Subject 2',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $busyQuarter = $this->makeRecordAndQuarter($teacher, $section, $otherSubject);
        for ($i = 1; $i <= 3; $i++) {
            $a = $this->makeAssessment($busyQuarter, $i, "Other Subject {$i}");
            $a->update(['activity_date' => '2026-09-07']);
        }

        $quarter = $this->makeRecordAndQuarter($teacher, $section, $subject);
        $existing = $this->makeAssessment($quarter, 1, 'Existing Quiz');
        $existing->update(['activity_date' => '2026-09-01']);

        // MOVING an existing row's date onto the over-cap day is a real
        // change (_date_changed = true) and must still be blocked.
        $payload = $this->payloadFor($existing);
        $payload['activity_date'] = '2026-09-07';

        $response = $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $quarter->class_record_id, 'q' => 1]), [
                'assessments' => [$payload],
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('WAT limit', $response->json('message'));
        $this->assertDatabaseHas('class_record_assessments', [
            'id' => $existing->id, 'activity_date' => '2026-09-01',
        ]);
    }

    public function test_apply_to_sections_skips_target_that_would_exceed_the_daily_graded_cap(): void
    {
        $teacher = User::factory()->create();
        $subject = $this->makeSubject();
        $targetSection = $this->makeSection();

        // Existing load already on the target section from an unrelated subject —
        // the daily cap pools by section regardless of subject.
        $otherSubject = Subject::create([
            'school_year_id' => $this->sy->id, 'code' => 'SUBJ2', 'name' => 'Subject 2',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $busyQuarter = $this->makeRecordAndQuarter($teacher, $targetSection, $otherSubject);
        for ($i = 1; $i <= 3; $i++) {
            $a = $this->makeAssessment($busyQuarter, $i, "Existing {$i}");
            $a->update(['activity_date' => '2026-09-07']);
        }

        $sourceQuarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $subject);
        $srcAssessment = $this->makeAssessment($sourceQuarter, 1, 'New Quiz');
        $srcAssessment->update(['activity_date' => '2026-09-07']);

        $targetQuarter = $this->makeRecordAndQuarter($teacher, $targetSection, $subject);

        $response = $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.apply-to-sections', [
                'classRecord' => $sourceQuarter->class_record_id, 'q' => 1,
            ]), ['target_class_record_ids' => [$targetQuarter->class_record_id]])
            ->assertOk()
            ->assertJsonCount(0, 'applied')
            ->assertJsonCount(1, 'skipped');

        $this->assertStringContainsString('WAT limit', $response->json('skipped.0.reason'));
    }

    public function test_apply_to_sections_skips_target_when_source_date_is_past_the_plotting_deadline(): void
    {
        $teacher = User::factory()->create();
        $subject = $this->makeSubject();

        $sourceQuarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $subject);
        $assessment = $this->makeAssessment($sourceQuarter, 1, 'Quiz 1');
        $assessment->update(['activity_date' => '2020-01-06']);

        $targetQuarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $subject);

        $response = $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.apply-to-sections', [
                'classRecord' => $sourceQuarter->class_record_id, 'q' => 1,
            ]), ['target_class_record_ids' => [$targetQuarter->class_record_id]])
            ->assertOk()
            ->assertJsonCount(0, 'applied')
            ->assertJsonCount(1, 'skipped');

        $this->assertStringContainsString('plotting deadline', $response->json('skipped.0.reason'));
    }

    public function test_apply_to_sections_skips_target_belonging_to_a_different_teacher(): void
    {
        $teacher = User::factory()->create();
        $otherTeacher = User::factory()->create();
        $subject = $this->makeSubject();

        $sourceQuarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $subject);
        $assessment = $this->makeAssessment($sourceQuarter, 1, 'Quiz 1');
        $assessment->update(['activity_date' => '2026-09-07']);

        $targetQuarter = $this->makeRecordAndQuarter($otherTeacher, $this->makeSection(), $subject);

        $response = $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.apply-to-sections', [
                'classRecord' => $sourceQuarter->class_record_id, 'q' => 1,
            ]), ['target_class_record_ids' => [$targetQuarter->class_record_id]])
            ->assertOk()
            ->assertJsonCount(0, 'applied')
            ->assertJsonCount(1, 'skipped');

        $this->assertSame('You do not have access to that class record.', $response->json('skipped.0.reason'));
    }

    public function test_apply_to_sections_applies_valid_targets_and_skips_invalid_ones_in_the_same_batch(): void
    {
        $teacher = User::factory()->create();
        $subject = $this->makeSubject();

        $sourceQuarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $subject);
        $assessment = $this->makeAssessment($sourceQuarter, 1, 'Quiz 1');
        $assessment->update(['activity_date' => '2026-09-07']);

        $goodTargetQuarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $subject);

        $busyTargetQuarter = $this->makeRecordAndQuarter($teacher, $this->makeSection(), $subject);
        $this->makeAssessment($busyTargetQuarter, 1, 'Already Here');

        $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.apply-to-sections', [
                'classRecord' => $sourceQuarter->class_record_id, 'q' => 1,
            ]), ['target_class_record_ids' => [
                $goodTargetQuarter->class_record_id, $busyTargetQuarter->class_record_id,
            ]])
            ->assertOk()
            ->assertJsonCount(1, 'applied')
            ->assertJsonCount(1, 'skipped');

        $this->assertDatabaseHas('class_record_assessments', [
            'class_record_quarter_id' => $goodTargetQuarter->id,
            'title'                   => 'Quiz 1',
        ]);
    }
}
