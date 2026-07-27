<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\DesignationCategory;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\User;
use App\Services\FacultyLoading\HeadAdvisoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the Homeroom Coordinator override.
 *
 * The override is a SEPARATE, independently credited designation
 * (HRC-{section_code}) from the adviser's own HRA-/HAC- one — both are
 * held at once and both show their own Load Assignment row. Only WAT
 * access/print (see WeeklyAssessmentTrackerTest) treats the override as
 * authoritative for the section — the adviser keeps their Load Assignment
 * credit regardless.
 */
class HeadAdvisoryServiceHomeroomCoordinatorTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private AcademicTerm $term;

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
    }

    private function makeSection(array $overrides = []): Section
    {
        return Section::create(array_merge([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $this->sy->id,
            'school_year_id' => $this->sy->id, 'is_active' => true,
        ], $overrides));
    }

    private function hrAssignments(Section $section): \Illuminate\Support\Collection
    {
        $categoryIds = DesignationCategory::whereIn('code', ['HR_ADV', 'HR_ACAD'])->pluck('id');

        return LoadAssignment::where('section_id', $section->id)
            ->where('academic_term_id', $this->term->id)
            ->whereHas('designation', fn ($q) => $q->whereIn('designation_category_id', $categoryIds))
            ->with('designation')
            ->get();
    }

    public function test_setting_an_override_adds_the_coordinator_without_removing_the_advisers_row(): void
    {
        $adviser = User::factory()->create();
        $coordinator = User::factory()->create();
        $advisory = app(HeadAdvisoryService::class);

        $section = $this->makeSection(['adviser' => $adviser->id]);
        $advisory->syncSectionAdviser($section, null);

        $section->update(['homeroom_coordinator_id' => $coordinator->id]);
        $advisory->syncHomeroomCoordinator($section->fresh(), null);

        $assignments = $this->hrAssignments($section);
        $this->assertCount(2, $assignments, 'Adviser and coordinator must both be credited — two separate designations.');

        $byUser = $assignments->keyBy('user_id');
        $this->assertTrue($byUser->has($adviser->id), 'Adviser must keep their HRA-/HAC- row.');
        $this->assertTrue($byUser->has($coordinator->id), 'Coordinator must get their own HRC- row.');
        $this->assertStringStartsWith('HRC-', $byUser[$coordinator->id]->designation->code);
        $this->assertStringStartsNotWith('HRC-', $byUser[$adviser->id]->designation->code);
    }

    public function test_changing_adviser_while_an_override_is_active_does_not_touch_the_coordinators_row(): void
    {
        $originalAdviser = User::factory()->create();
        $newAdviser = User::factory()->create();
        $coordinator = User::factory()->create();
        $advisory = app(HeadAdvisoryService::class);

        $section = $this->makeSection(['adviser' => $originalAdviser->id, 'homeroom_coordinator_id' => $coordinator->id]);
        $advisory->syncSectionAdviser($section, null);
        $advisory->syncHomeroomCoordinator($section->fresh(), null);

        $section->update(['adviser' => $newAdviser->id]);
        $advisory->syncSectionAdviser($section->fresh(), $originalAdviser->id);

        $assignments = $this->hrAssignments($section);
        $this->assertCount(2, $assignments, 'Still exactly one adviser row + one coordinator row.');

        $byUser = $assignments->keyBy('user_id');
        $this->assertTrue($byUser->has($newAdviser->id), 'New adviser must be credited.');
        $this->assertFalse($byUser->has($originalAdviser->id), 'Old adviser must lose their row.');
        $this->assertTrue($byUser->has($coordinator->id), 'Coordinator must be untouched by the adviser change.');
    }

    public function test_clearing_the_override_removes_only_the_coordinators_row(): void
    {
        $adviser = User::factory()->create();
        $coordinator = User::factory()->create();
        $advisory = app(HeadAdvisoryService::class);

        $section = $this->makeSection(['adviser' => $adviser->id, 'homeroom_coordinator_id' => $coordinator->id]);
        $advisory->syncSectionAdviser($section, null);
        $advisory->syncHomeroomCoordinator($section->fresh(), null);

        $section->update(['homeroom_coordinator_id' => null]);
        $advisory->syncHomeroomCoordinator($section->fresh(), $coordinator->id);

        $assignments = $this->hrAssignments($section);
        $this->assertCount(1, $assignments, 'Clearing the override must remove the coordinator row and leave the adviser untouched.');
        $this->assertSame($adviser->id, $assignments->first()->user_id);
    }

    public function test_same_person_can_hold_both_adviser_and_coordinator_roles_independently(): void
    {
        $person = User::factory()->create();
        $advisory = app(HeadAdvisoryService::class);

        $section = $this->makeSection(['adviser' => $person->id, 'homeroom_coordinator_id' => $person->id]);
        $advisory->syncSectionAdviser($section, null);
        $advisory->syncHomeroomCoordinator($section->fresh(), null);

        $this->assertCount(2, $this->hrAssignments($section), 'One person can hold both designations at once — each is its own row.');

        // Clearing only the coordinator override must not touch their adviser row.
        $section->update(['homeroom_coordinator_id' => null]);
        $advisory->syncHomeroomCoordinator($section->fresh(), $person->id);

        $remaining = $this->hrAssignments($section);
        $this->assertCount(1, $remaining);
        $this->assertSame($person->id, $remaining->first()->user_id);
        $this->assertStringStartsNotWith('HRC-', $remaining->first()->designation->code);
    }
}
