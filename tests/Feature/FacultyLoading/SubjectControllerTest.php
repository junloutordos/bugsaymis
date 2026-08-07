<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `subject_group` is the switch that lets a PEHM-style class record be
 * shared across teachers (see ClassRecordPehmCoTeachingTest). This covers
 * that the Subject admin form can actually persist it.
 */
class SubjectControllerTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
    }

    private function userWith(string $permission): User
    {
        $perm = Permission::firstOrCreate(
            ['name' => $permission],
            ['module' => 'FacultyLoading', 'description' => $permission],
        );
        $role = Role::create(['name' => 'SubjectRole_'.uniqid()]);
        $role->permissions()->attach($perm->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    private function subjectPayload(array $overrides = []): array
    {
        return array_merge([
            'school_year_id' => $this->sy->id,
            'code' => 'PE1-G7',
            'name' => 'Physical Education 1',
            'credit_units' => 3,
            'lecture_hours' => 3,
            'load_units' => 3,
            'subject_type' => 'lecture',
            'grade_level' => 7,
            'semester' => 'both',
            'sessions_per_week' => 2,
            'minutes_per_session' => 60,
            'is_active' => true,
        ], $overrides);
    }

    public function test_subject_group_is_saved_on_create(): void
    {
        $user = $this->userWith('faculty_loading.subjects');

        $this->actingAs($user)
            ->post(route('faculty-loading.subjects.store'), $this->subjectPayload(['subject_group' => 'PEHM']))
            ->assertRedirect();

        $this->assertDatabaseHas('subjects', [
            'code' => 'PE1-G7', 'subject_group' => 'PEHM',
        ]);
    }

    public function test_subject_group_is_saved_on_update(): void
    {
        $user = $this->userWith('faculty_loading.subjects');
        $subject = Subject::create($this->subjectPayload());

        $this->actingAs($user)
            ->put(route('faculty-loading.subjects.update', $subject->id), $this->subjectPayload(['subject_group' => 'PEHM']))
            ->assertRedirect();

        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id, 'subject_group' => 'PEHM',
        ]);
    }
}
