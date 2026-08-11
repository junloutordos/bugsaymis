<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Services\Learn\CourseCoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CourseCoverServiceTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');

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
        $this->course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
    }

    public function test_upload_decodes_base64_stores_to_s3_and_clears_any_preset(): void
    {
        $this->course->update(['cover_preset' => 'sky-wave']);
        $service = app(CourseCoverService::class);
        $dataUri = 'data:image/png;base64,' . base64_encode('fake png bytes');

        $service->upload($this->course, $dataUri);

        $this->course->refresh();
        Storage::disk('s3')->assertExists($this->course->cover_photo_s3_key);
        $this->assertStringStartsWith("Learn/{$this->course->id}/cover-", $this->course->cover_photo_s3_key);
        $this->assertNull($this->course->cover_preset);
    }

    public function test_upload_rejects_non_image_mime_types(): void
    {
        $service = app(CourseCoverService::class);

        foreach (['text/html', 'application/pdf', 'image/svg+xml'] as $mime) {
            $dataUri = "data:{$mime};base64," . base64_encode('<script>alert(1)</script>');

            try {
                $service->upload($this->course, $dataUri);
                $this->fail("Expected upload of {$mime} to be rejected.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('photo', $e->errors());
            }
        }
    }

    public function test_upload_deletes_the_previous_cover_object(): void
    {
        $service = app(CourseCoverService::class);
        $service->upload($this->course, 'data:image/png;base64,' . base64_encode('first'));
        $firstKey = $this->course->refresh()->cover_photo_s3_key;

        $service->upload($this->course, 'data:image/png;base64,' . base64_encode('second'));

        Storage::disk('s3')->assertMissing($firstKey);
    }

    public function test_set_preset_stores_the_key_and_clears_any_photo(): void
    {
        $service = app(CourseCoverService::class);
        $service->upload($this->course, 'data:image/png;base64,' . base64_encode('photo'));
        $photoKey = $this->course->refresh()->cover_photo_s3_key;

        $service->setPreset($this->course, 'ocean-deep');

        $this->course->refresh();
        $this->assertSame('ocean-deep', $this->course->cover_preset);
        $this->assertNull($this->course->cover_photo_s3_key);
        Storage::disk('s3')->assertMissing($photoKey);
    }

    public function test_set_preset_rejects_an_unknown_preset_key(): void
    {
        $service = app(CourseCoverService::class);

        $this->expectException(ValidationException::class);
        $service->setPreset($this->course, 'not-a-real-preset');
    }

    public function test_stream_response_serves_the_photo_bytes(): void
    {
        $service = app(CourseCoverService::class);
        $service->upload($this->course, 'data:image/jpeg;base64,' . base64_encode('jpeg bytes'));

        $response = $service->streamResponse($this->course->fresh());

        $this->assertSame('jpeg bytes', $response->getContent());
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
    }

    public function test_stream_response_404s_when_no_cover_photo_is_set(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        app(CourseCoverService::class)->streamResponse($this->course);
    }
}
