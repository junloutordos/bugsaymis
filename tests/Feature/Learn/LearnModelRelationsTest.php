<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\File as LearnFile;
use App\Models\Learn\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearnModelRelationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCourse(): Course
    {
        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);

        return Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
    }

    public function test_course_has_modules_and_announcements(): void
    {
        $course = $this->makeCourse();
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $user = User::factory()->create();
        $course->announcements()->create([
            'title' => 'Welcome', 'body' => 'Hi class', 'posted_by' => $user->id, 'posted_at' => now(),
        ]);

        $this->assertCount(1, $course->fresh()->modules);
        $this->assertCount(1, $course->fresh()->announcements);
        $this->assertTrue($module->course->is($course));
    }

    public function test_module_item_resolves_page_via_polymorphic_relation(): void
    {
        $course = $this->makeCourse();
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $page = Page::create(['title' => 'Syllabus', 'body' => '<p>Hi</p>']);
        $item = $page->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);

        $this->assertInstanceOf(Page::class, $item->itemable);
        $this->assertSame('Syllabus', $item->itemable->title);
        $this->assertFalse($item->isPublished());

        $item->update(['published_at' => now()]);
        $this->assertTrue($item->fresh()->isPublished());
    }

    public function test_module_item_resolves_file_via_polymorphic_relation(): void
    {
        $course = $this->makeCourse();
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $file = LearnFile::create([
            'title' => 'Handout.pdf', 's3_key' => 'Learn/1/abc.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 1024,
        ]);
        $item = $file->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 1]);

        $this->assertInstanceOf(LearnFile::class, $item->itemable);
        $this->assertSame('Learn/1/abc.pdf', $item->itemable->s3_key);
    }

    public function test_module_is_published_reflects_published_at(): void
    {
        $course = $this->makeCourse();
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);

        $this->assertFalse($module->isPublished());
        $module->update(['published_at' => now()]);
        $this->assertTrue($module->fresh()->isPublished());
    }
}
