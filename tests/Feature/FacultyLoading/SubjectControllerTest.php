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

    public function test_is_stem_is_saved_on_create_and_update(): void
    {
        $user = $this->userWith('faculty_loading.subjects.manage');

        $this->actingAs($user)
            ->post(route('faculty-loading.subjects.store'), $this->subjectPayload(['is_stem' => true]))
            ->assertRedirect();

        $subject = Subject::where('code', 'PE1-G7')->firstOrFail();
        $this->assertTrue($subject->is_stem);

        $this->actingAs($user)
            ->put(route('faculty-loading.subjects.update', $subject->id), $this->subjectPayload(['is_stem' => false]))
            ->assertRedirect();

        $this->assertFalse($subject->fresh()->is_stem);
    }

    public function test_subject_group_is_saved_on_create(): void
    {
        $user = $this->userWith('faculty_loading.subjects.manage');

        $this->actingAs($user)
            ->post(route('faculty-loading.subjects.store'), $this->subjectPayload(['subject_group' => 'PEHM']))
            ->assertRedirect();

        $this->assertDatabaseHas('subjects', [
            'code' => 'PE1-G7', 'subject_group' => 'PEHM',
        ]);
    }

    public function test_subject_group_is_saved_on_update(): void
    {
        $user = $this->userWith('faculty_loading.subjects.manage');
        $subject = Subject::create($this->subjectPayload());

        $this->actingAs($user)
            ->put(route('faculty-loading.subjects.update', $subject->id), $this->subjectPayload(['subject_group' => 'PEHM']))
            ->assertRedirect();

        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id, 'subject_group' => 'PEHM',
        ]);
    }

    public function test_copy_from_year_carries_is_stem(): void
    {
        $user = $this->userWith('faculty_loading.subjects.manage');
        Subject::create($this->subjectPayload(['is_stem' => true]));

        $targetYear = SchoolYear::create([
            'name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-06-30',
            'is_current' => false, 'status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('faculty-loading.subjects.copy-from-year'), [
                'source_school_year_id' => $this->sy->id,
                'target_school_year_id' => $targetYear->id,
            ])
            ->assertRedirect();

        $copied = Subject::where('school_year_id', $targetYear->id)->where('code', 'PE1-G7')->firstOrFail();
        $this->assertTrue($copied->is_stem);
    }

    public function test_view_only_user_can_view_index_but_not_create(): void
    {
        $user = $this->userWith('faculty_loading.subjects.view');

        $this->actingAs($user)
            ->get(route('faculty-loading.subjects.index'))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('faculty-loading.subjects.store'), $this->subjectPayload())
            ->assertForbidden();
    }

    public function test_view_only_user_cannot_update_or_delete(): void
    {
        $user = $this->userWith('faculty_loading.subjects.view');
        $subject = Subject::create($this->subjectPayload());

        $this->actingAs($user)
            ->put(route('faculty-loading.subjects.update', $subject->id), $this->subjectPayload(['subject_group' => 'PEHM']))
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('faculty-loading.subjects.destroy', $subject->id))
            ->assertForbidden();
    }

    public function test_index_includes_assigned_faculty_email_from_users_and_mobile_from_pds(): void
    {
        $manageUser = $this->userWith('faculty_loading.subjects.manage');
        $subject = Subject::create($this->subjectPayload());

        $term = \App\Models\FacultyLoading\AcademicTerm::create([
            'school_year_id' => $this->sy->id, 'name' => '1st Semester',
            'term_type' => '1st_semester', 'start_date' => '2025-08-01',
            'end_date' => '2025-12-31', 'is_current' => true,
        ]);

        $faculty = User::factory()->create(['name' => 'Juan Dela Cruz', 'email' => 'juan@crc.pshs.edu.ph']);
        $pds = \App\Models\Pds::create(['user_id' => $faculty->id]);
        \App\Models\PDSPersonalInfo::create([
            'pds_id' => $pds->id,
            'surname' => 'Dela Cruz',
            'first_name' => 'Juan',
            'email_address' => 'juan.personal@example.com',
            'mobile_no' => '09171234567',
        ]);

        $facultyLoad = \App\Models\FacultyLoading\FacultyLoad::create([
            'user_id' => $faculty->id,
            'school_year_id' => $this->sy->id,
            'academic_term_id' => $term->id,
        ]);

        \App\Models\FacultyLoading\LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id,
            'user_id' => $faculty->id,
            'school_year_id' => $this->sy->id,
            'subject_id' => $subject->id,
            'academic_term_id' => $term->id,
            'assignment_type' => 'teaching',
        ]);

        $this->actingAs($manageUser)
            ->get(route('faculty-loading.subjects.index', ['term_id' => $term->id]))
            ->assertOk()
            ->assertInertia(function ($page) use ($subject) {
                $matched = collect($page->toArray()['props']['subjects'])->firstWhere('id', $subject->id);
                $facultyEntry = $matched['faculty'][0];

                return $facultyEntry['email'] === 'juan@crc.pshs.edu.ph'
                    && $facultyEntry['mobile_no'] === '09171234567';
            });
    }
}
