<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ModuleAndItemControllerTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private AcademicTerm $term;
    private Section $section;
    private Subject $subject;
    private User $teacher;
    private User $stranger;
    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');

        $this->sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $this->term = AcademicTerm::create([
            'school_year_id' => $this->sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $this->section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $this->sy->id,
            'school_year_id' => $this->sy->id, 'is_active' => true,
        ]);
        $this->subject = Subject::create([
            'school_year_id' => $this->sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $this->teacher = User::factory()->create();
        $this->stranger = User::factory()->create();

        $facultyLoad = FacultyLoad::create([
            'user_id' => $this->teacher->id, 'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $this->teacher->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
            'assignment_type' => 'teaching', 'subject_id' => $this->subject->id,
            'section_id' => $this->section->id, 'load_units' => 3,
        ]);

        $this->course = Course::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);
    }

    public function test_instructor_can_create_a_module_stranger_cannot(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('learn.modules.store', $this->course), ['title' => 'Week 1'])
            ->assertRedirect();
        $this->assertDatabaseHas('learn_modules', ['learn_course_id' => $this->course->id, 'title' => 'Week 1']);

        $this->actingAs($this->stranger)
            ->post(route('learn.modules.store', $this->course), ['title' => 'Hack'])
            ->assertForbidden();
    }

    public function test_instructor_can_toggle_module_publish(): void
    {
        $module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0]);

        $this->actingAs($this->teacher)->patch(route('learn.modules.publish', $module))->assertRedirect();
        $this->assertTrue($module->fresh()->isPublished());

        $this->actingAs($this->teacher)->patch(route('learn.modules.publish', $module))->assertRedirect();
        $this->assertFalse($module->fresh()->isPublished());
    }

    public function test_instructor_can_reorder_modules(): void
    {
        $first = $this->course->modules()->create(['title' => 'A', 'position' => 0]);
        $second = $this->course->modules()->create(['title' => 'B', 'position' => 1]);

        $this->actingAs($this->teacher)
            ->put(route('learn.modules.reorder', $this->course), ['module_ids' => [$second->id, $first->id]])
            ->assertRedirect();

        $this->assertSame(0, $second->fresh()->position);
        $this->assertSame(1, $first->fresh()->position);
    }

    public function test_instructor_can_delete_a_module(): void
    {
        $module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0]);

        $this->actingAs($this->teacher)->delete(route('learn.modules.destroy', $module))->assertRedirect();
        $this->assertDatabaseMissing('learn_modules', ['id' => $module->id]);
    }

    public function test_instructor_can_add_a_page_item(): void
    {
        $module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0]);

        $this->actingAs($this->teacher)->post(route('learn.items.store-page', $module), [
            'title' => 'Intro', 'body' => '<p>Hello</p>', 'video_url' => null,
        ])->assertRedirect();

        $this->assertDatabaseHas('learn_pages', ['title' => 'Intro']);
        $this->assertDatabaseHas('learn_module_items', ['learn_module_id' => $module->id, 'itemable_type' => \App\Models\Learn\Page::class]);
    }

    public function test_instructor_can_add_a_file_item(): void
    {
        $module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $dataUri = 'data:application/pdf;base64,' . base64_encode('fake pdf');

        $this->actingAs($this->teacher)->post(route('learn.items.store-file', $module), [
            'title' => 'Handout.pdf', 'file_base64' => $dataUri,
        ])->assertRedirect();

        $this->assertDatabaseHas('learn_files', ['title' => 'Handout.pdf']);
        $this->assertDatabaseHas('learn_module_items', ['learn_module_id' => $module->id, 'itemable_type' => \App\Models\Learn\File::class]);
    }

    public function test_stranger_cannot_add_items_to_a_module(): void
    {
        $module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0]);

        $this->actingAs($this->stranger)->post(route('learn.items.store-page', $module), [
            'title' => 'Intro', 'body' => '<p>Hi</p>',
        ])->assertForbidden();
    }

    public function test_instructor_can_toggle_item_publish_reorder_and_delete(): void
    {
        $module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $page = \App\Models\Learn\Page::create(['title' => 'Intro']);
        $item1 = $page->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);
        $page2 = \App\Models\Learn\Page::create(['title' => 'Outro']);
        $item2 = $page2->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 1]);

        $this->actingAs($this->teacher)->patch(route('learn.items.publish', $item1))->assertRedirect();
        $this->assertTrue($item1->fresh()->isPublished());

        $this->actingAs($this->teacher)
            ->put(route('learn.items.reorder', $module), ['item_ids' => [$item2->id, $item1->id]])
            ->assertRedirect();
        $this->assertSame(0, $item2->fresh()->position);
        $this->assertSame(1, $item1->fresh()->position);

        $this->actingAs($this->teacher)->delete(route('learn.items.destroy', $item1))->assertRedirect();
        $this->assertDatabaseMissing('learn_module_items', ['id' => $item1->id]);
        $this->assertDatabaseMissing('learn_pages', ['id' => $page->id]);
    }
}
