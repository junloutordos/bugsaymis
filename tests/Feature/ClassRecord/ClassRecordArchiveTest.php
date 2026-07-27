<?php

namespace Tests\Feature\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassRecordArchiveTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private GradingOption $option;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $this->option = GradingOption::create(['name' => 'Default', 'is_active' => true]);
    }

    private function admin(): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'class-records.admin'],
            ['module' => 'Class Records', 'description' => 'Admin'],
        );
        $role = Role::create(['name' => 'ClassRecordAdmin_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create(['signature_pin' => bcrypt('123456')]);
        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    private function teacher(?string $pin = '123456'): User
    {
        return User::factory()->create([
            'signature_pin' => $pin ? bcrypt($pin) : null,
        ]);
    }

    private function record(User $teacher, string $status = 'draft'): ClassRecord
    {
        return ClassRecord::create([
            'subject_id' => null,
            'section_id' => null,
            'grading_option_id' => $this->option->id,
            'school_year_id' => $this->sy->id,
            'school_year' => $this->sy->name,
            'subject_name' => 'Mathematics 6',
            'year_level_section' => 'G-6 Del Mundo',
            'teacher_id' => $teacher->id,
            'status' => $status,
        ]);
    }

    public function test_owner_can_archive_own_record_with_correct_pin(): void
    {
        $teacher = $this->teacher();
        $record = $this->record($teacher, 'submitted');

        $this->actingAs($teacher)
            ->deleteJson(route('class-records.destroy', $record->id), ['pin' => '123456'])
            ->assertOk();

        $record->refresh();
        $this->assertSame('archived', $record->status);
        $this->assertSame('submitted', $record->pre_archive_status);
        $this->assertNotNull($record->archived_at);
        $this->assertSame($teacher->id, $record->archived_by_id);
        // Not hard-deleted — row still exists.
        $this->assertDatabaseHas('class_records', ['id' => $record->id, 'status' => 'archived']);
    }

    public function test_wrong_pin_does_not_archive(): void
    {
        $teacher = $this->teacher();
        $record = $this->record($teacher);

        $this->actingAs($teacher)
            ->deleteJson(route('class-records.destroy', $record->id), ['pin' => '000000'])
            ->assertStatus(422);

        $this->assertSame('draft', $record->fresh()->status);
    }

    public function test_missing_pin_setup_blocks_archive(): void
    {
        $teacher = $this->teacher(null);
        $record = $this->record($teacher);

        $this->actingAs($teacher)
            ->deleteJson(route('class-records.destroy', $record->id), ['pin' => '123456'])
            ->assertStatus(422);

        $this->assertSame('draft', $record->fresh()->status);
    }

    public function test_non_owner_non_admin_cannot_archive(): void
    {
        $owner = $this->teacher();
        $stranger = $this->teacher();
        $record = $this->record($owner);

        $this->actingAs($stranger)
            ->deleteJson(route('class-records.destroy', $record->id), ['pin' => '123456'])
            ->assertForbidden();

        $this->assertSame('draft', $record->fresh()->status);
    }

    public function test_admin_can_archive_any_record(): void
    {
        $admin = $this->admin();
        $teacher = $this->teacher();
        $record = $this->record($teacher, 'checked');

        $this->actingAs($admin)
            ->deleteJson(route('class-records.destroy', $record->id), ['pin' => '123456'])
            ->assertOk();

        $this->assertSame('archived', $record->fresh()->status);
        $this->assertSame('checked', $record->fresh()->pre_archive_status);
    }

    public function test_restore_returns_record_to_pre_archive_status(): void
    {
        $teacher = $this->teacher();
        $record = $this->record($teacher, 'submitted');

        $this->actingAs($teacher)
            ->deleteJson(route('class-records.destroy', $record->id), ['pin' => '123456'])
            ->assertOk();

        $this->actingAs($teacher)
            ->postJson(route('class-records.restore', $record->id))
            ->assertOk();

        $record->refresh();
        $this->assertSame('submitted', $record->status);
        $this->assertNull($record->archived_at);
        $this->assertNull($record->archived_by_id);
        $this->assertNull($record->pre_archive_status);
    }

    public function test_archived_records_excluded_from_default_index_but_visible_under_status_filter(): void
    {
        $admin = $this->admin();
        $active = $this->record($this->teacher());
        $archived = $this->record($this->teacher());

        $this->actingAs($admin)
            ->deleteJson(route('class-records.destroy', $archived->id), ['pin' => '123456'])
            ->assertOk();

        $default = $this->actingAs($admin)->getJson(route('class-records.index'));
        $default->assertOk();
        $defaultIds = collect($default->json('data'))->pluck('id');
        $this->assertTrue($defaultIds->contains($active->id));
        $this->assertFalse($defaultIds->contains($archived->id));

        $archivedList = $this->actingAs($admin)->getJson(route('class-records.index', ['status' => 'archived']));
        $archivedIds = collect($archivedList->json('data'))->pluck('id');
        $this->assertTrue($archivedIds->contains($archived->id));
        $this->assertFalse($archivedIds->contains($active->id));
    }

    public function test_cannot_archive_already_archived_record(): void
    {
        $teacher = $this->teacher();
        $record = $this->record($teacher);

        $this->actingAs($teacher)
            ->deleteJson(route('class-records.destroy', $record->id), ['pin' => '123456'])
            ->assertOk();

        $this->actingAs($teacher)
            ->deleteJson(route('class-records.destroy', $record->id), ['pin' => '123456'])
            ->assertStatus(422);
    }

    public function test_archived_record_does_not_appear_as_a_copy_from_source(): void
    {
        $teacher = $this->teacher();
        $archived = $this->record($teacher);
        $new = $this->record($teacher);

        $this->actingAs($teacher)
            ->deleteJson(route('class-records.destroy', $archived->id), ['pin' => '123456'])
            ->assertOk();

        $this->actingAs($teacher)
            ->get(route('class-records.page.show', $new->id))
            ->assertInertia(fn ($page) => $page
                ->component('ClassRecord/Show')
                ->where('sameSubjectRecords', []));
    }

    public function test_copy_from_record_rejects_an_archived_source(): void
    {
        $teacher = $this->teacher();
        $archived = $this->record($teacher);
        $new = $this->record($teacher);

        \App\Models\ClassRecord\ClassRecordQuarter::create([
            'class_record_id' => $archived->id, 'quarter' => 1, 'is_locked' => false,
        ]);
        \App\Models\ClassRecord\ClassRecordQuarter::create([
            'class_record_id' => $new->id, 'quarter' => 1, 'is_locked' => false,
        ]);

        $this->actingAs($teacher)
            ->deleteJson(route('class-records.destroy', $archived->id), ['pin' => '123456'])
            ->assertOk();

        $this->actingAs($teacher)
            ->postJson(route('class-records.assessments.copy-from-record', ['classRecord' => $new->id, 'q' => 1]), [
                'source_class_record_id' => $archived->id,
                'source_quarter' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'That class record has been archived and can no longer be used as a copy source.']);
    }
}
