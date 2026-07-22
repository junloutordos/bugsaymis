<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\ClassScheduleScopeLock;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\FacultyLoading\ClassScheduleScopeLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClassScheduleScopeLockTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $otherChief;

    private AcademicTerm $term;

    private SchoolYear $schoolYear;

    private Section $calcium;

    private Section $lithium;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::firstOrCreate(
            ['name' => 'faculty_loading.manage'],
            ['module' => 'FacultyLoading', 'description' => 'Manage faculty loading'],
        );
        $role = Role::firstOrCreate(['name' => 'CID Chief']);
        $role->permissions()->syncWithoutDetaching($permission->id);

        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->owner->roles()->attach($role->id);
        $this->otherChief = User::factory()->create(['email_verified_at' => now()]);
        $this->otherChief->roles()->attach($role->id);

        $schoolYear = SchoolYear::create([
            'name' => '2026-2027',
            'start_date' => '2026-08-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
            'status' => 'active',
        ]);
        $this->schoolYear = $schoolYear;
        $this->term = AcademicTerm::create([
            'school_year_id' => $schoolYear->id,
            'name' => '1st Semester',
            'term_type' => '1st_semester',
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
        ]);
        $this->calcium = $this->section($schoolYear, 'Calcium', 9);
        $this->lithium = $this->section($schoolYear, 'Lithium', 9);
    }

    public function test_section_lock_is_audited_and_only_owner_can_unlock_it(): void
    {
        $response = $this->actingAs($this->owner)->postJson(route('faculty-loading.schedules.scope-locks.store'), [
            'academic_term_id' => $this->term->id,
            'scope_type' => 'section',
            'section_id' => $this->calcium->id,
        ])->assertOk();

        $lockId = $response->json('lock.id');
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->owner->id,
            'action' => 'lock_schedule_scope',
            'auditable_id' => $lockId,
        ]);

        $this->actingAs($this->otherChief)
            ->deleteJson(route('faculty-loading.schedules.scope-locks.destroy', $lockId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('schedule_lock');

        $this->actingAs($this->owner)
            ->deleteJson(route('faculty-loading.schedules.scope-locks.destroy', $lockId))
            ->assertOk();

        $this->assertDatabaseMissing('class_schedule_scope_locks', ['id' => $lockId]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->owner->id,
            'action' => 'unlock_schedule_scope',
            'auditable_id' => $lockId,
        ]);
    }

    public function test_grade_lock_covers_every_section_and_rejects_overlapping_locks(): void
    {
        $this->actingAs($this->owner)->postJson(route('faculty-loading.schedules.scope-locks.store'), [
            'academic_term_id' => $this->term->id,
            'scope_type' => 'grade',
            'grade_level' => 9,
        ])->assertOk();

        $locks = app(ClassScheduleScopeLockService::class)->locksForSections(
            $this->term->id,
            collect([$this->calcium, $this->lithium]),
        );
        $this->assertSame('grade', $locks[$this->calcium->id]['scope_type']);
        $this->assertSame('grade', $locks[$this->lithium->id]['scope_type']);

        $this->actingAs($this->otherChief)->postJson(route('faculty-loading.schedules.scope-locks.store'), [
            'academic_term_id' => $this->term->id,
            'scope_type' => 'section',
            'section_id' => $this->calcium->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('schedule_lock');
    }

    public function test_administrator_can_unlock_another_users_lock(): void
    {
        $lock = ClassScheduleScopeLock::create([
            'academic_term_id' => $this->term->id,
            'scope_type' => 'whole',
            'scope_key' => 'whole',
            'locked_by' => $this->owner->id,
            'locked_at' => now(),
        ]);
        $administrator = User::factory()->create(['email_verified_at' => now()]);
        $administrator->roles()->attach(Role::firstOrCreate(['name' => 'Administrator'])->id);

        $this->actingAs($administrator)
            ->deleteJson(route('faculty-loading.schedules.scope-locks.destroy', $lock))
            ->assertOk();

        $this->assertDatabaseMissing('class_schedule_scope_locks', ['id' => $lock->id]);
    }

    public function test_whole_schedule_lock_also_covers_entries_without_a_section(): void
    {
        ClassScheduleScopeLock::create([
            'academic_term_id' => $this->term->id,
            'scope_type' => 'whole',
            'scope_key' => 'whole',
            'locked_by' => $this->owner->id,
            'locked_at' => now(),
        ]);

        $this->expectException(ValidationException::class);

        app(ClassScheduleScopeLockService::class)->assertSectionUnlocked($this->term->id, null);
    }

    public function test_locked_section_rejects_consultation_changes(): void
    {
        ClassScheduleScopeLock::create([
            'academic_term_id' => $this->term->id,
            'scope_type' => 'section',
            'scope_key' => 'section:'.$this->calcium->id,
            'section_id' => $this->calcium->id,
            'locked_by' => $this->owner->id,
            'locked_at' => now(),
        ]);

        $this->actingAs($this->otherChief)
            ->patchJson(route('faculty-loading.bell-schedule.consultation.section', [
                'section' => $this->calcium,
                'day' => 'Tuesday',
            ]), [
                'academic_term_id' => $this->term->id,
                'start' => '14:00',
                'end' => '14:30',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('schedule_lock');
    }

    public function test_locked_section_rejects_direct_schedule_creation(): void
    {
        ClassScheduleScopeLock::create([
            'academic_term_id' => $this->term->id,
            'scope_type' => 'section',
            'scope_key' => 'section:'.$this->calcium->id,
            'section_id' => $this->calcium->id,
            'locked_by' => $this->owner->id,
            'locked_at' => now(),
        ]);
        $faculty = User::factory()->create(['email_verified_at' => now()]);
        $subject = Subject::create([
            'school_year_id' => $this->schoolYear->id,
            'code' => 'MATH9', 'name' => 'Mathematics 9', 'credit_units' => 3,
            'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 9, 'sessions_per_week' => 5, 'minutes_per_session' => 50,
            'is_active' => true,
        ]);
        $room = Classroom::create([
            'school_year_id' => $this->schoolYear->id,
            'name' => 'Room 9', 'code' => 'R9', 'classroom_type' => 'lecture',
            'capacity' => 40, 'is_available' => true,
        ]);

        $this->actingAs($this->otherChief)->post(route('faculty-loading.schedules.store'), [
            'faculty_id' => $faculty->id,
            'subject_id' => $subject->id,
            'section_id' => $this->calcium->id,
            'classroom_id' => $room->id,
            'school_year_id' => $this->schoolYear->id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '08:00',
            'end_time' => '08:50',
            'status' => 'tentative',
        ])->assertSessionHasErrors('schedule_lock');
    }

    public function test_complete_schedule_cannot_be_submitted_while_any_scope_lock_exists(): void
    {
        ClassScheduleScopeLock::create([
            'academic_term_id' => $this->term->id,
            'scope_type' => 'section',
            'scope_key' => 'section:'.$this->calcium->id,
            'section_id' => $this->calcium->id,
            'locked_by' => $this->owner->id,
            'locked_at' => now(),
        ]);

        $this->actingAs($this->owner)
            ->post(route('faculty-loading.schedules.approval.submit', $this->term), ['pin' => '123456'])
            ->assertSessionHasErrors('schedule_lock');
    }

    private function section(SchoolYear $schoolYear, string $name, int $grade): Section
    {
        return Section::create([
            'levelid' => $grade,
            'sectionname' => $name,
            'syid' => $schoolYear->id,
            'school_year_id' => $schoolYear->id,
            'is_active' => true,
        ]);
    }
}
