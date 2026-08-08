<?php

namespace Tests\Feature\Learn;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LearnSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_learn_courses_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_courses'));
        $this->assertTrue(Schema::hasColumns('learn_courses', [
            'id', 'subject_id', 'section_id', 'school_year_id', 'academic_term_id',
            'status', 'syllabus_body', 'created_at', 'updated_at',
        ]));
    }

    public function test_learn_modules_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_modules'));
        $this->assertTrue(Schema::hasColumns('learn_modules', [
            'id', 'learn_course_id', 'title', 'position', 'published_at',
        ]));
    }

    public function test_learn_module_items_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_module_items'));
        $this->assertTrue(Schema::hasColumns('learn_module_items', [
            'id', 'learn_module_id', 'itemable_type', 'itemable_id', 'position', 'published_at',
        ]));
    }

    public function test_learn_pages_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_pages'));
        $this->assertTrue(Schema::hasColumns('learn_pages', ['id', 'title', 'body', 'video_url']));
    }

    public function test_learn_files_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_files'));
        $this->assertTrue(Schema::hasColumns('learn_files', ['id', 'title', 's3_key', 'mime_type', 'size_bytes']));
    }

    public function test_learn_course_announcements_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_course_announcements'));
        $this->assertTrue(Schema::hasColumns('learn_course_announcements', [
            'id', 'learn_course_id', 'title', 'body', 'posted_by', 'posted_at',
        ]));
    }

    public function test_learn_courses_tuple_is_unique(): void
    {
        \App\Models\FacultyLoading\SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \Illuminate\Support\Facades\DB::table('learn_courses')->insert([
            ['subject_id' => 1, 'section_id' => 1, 'school_year_id' => 1, 'academic_term_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['subject_id' => 1, 'section_id' => 1, 'school_year_id' => 1, 'academic_term_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
