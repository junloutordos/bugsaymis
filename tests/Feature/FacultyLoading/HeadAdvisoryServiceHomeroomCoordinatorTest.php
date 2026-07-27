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
 * Before this feature, sections.adviser was the ONLY input to the
 * HR_ADV/HR_ACAD designation — HeadAdvisoryService::syncSectionAdviser()
 * auto-mirrored it 1:1, so the WAT form's "Homeroom Coordinator" always
 * resolved to the same person as the Section Adviser, with no way to make
 * them different people. These tests cover the override that decouples them.
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
            ->get();
    }

    public function test_setting_an_override_assigns_the_coordinator_and_removes_the_advisers_auto_synced_row(): void
    {
        $adviser = User::factory()->create();
        $coordinator = User::factory()->create();
        $advisory = app(HeadAdvisoryService::class);

        $section = $this->makeSection(['adviser' => $adviser->id]);
        $advisory->syncSectionAdviser($section, null);

        $this->assertSame($adviser->id, $this->hrAssignments($section)->first()->user_id);

        $section->update(['homeroom_coordinator_id' => $coordinator->id]);
        $advisory->syncHomeroomCoordinator($section->fresh(), null);

        $assignments = $this->hrAssignments($section);
        $this->assertCount(1, $assignments, 'Only one HR_ADV/HR_ACAD holder should exist per section — no duplicate rows.');
        $this->assertSame($coordinator->id, $assignments->first()->user_id);
    }

    public function test_changing_adviser_while_an_override_is_active_does_not_create_a_second_assignment(): void
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
        $this->assertCount(1, $assignments, 'Adviser change must not add a second holder while the coordinator override is active.');
        $this->assertSame($coordinator->id, $assignments->first()->user_id);
    }

    public function test_clearing_the_override_falls_back_to_the_current_adviser(): void
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
        $this->assertCount(1, $assignments, 'Clearing the override must not leave a stale coordinator row alongside the adviser fallback.');
        $this->assertSame($adviser->id, $assignments->first()->user_id);
    }
}
