<?php

namespace Tests\Feature\Learn;

use App\Services\Learn\CourseFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourseFileServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
    }

    public function test_upload_decodes_base64_and_stores_to_s3_never_public_disk(): void
    {
        $service = app(CourseFileService::class);
        $dataUri = 'data:application/pdf;base64,' . base64_encode('%PDF-1.4 fake pdf bytes');

        $file = $service->upload(1, 'Handout.pdf', $dataUri);

        $this->assertSame('Handout.pdf', $file->title);
        $this->assertSame('application/pdf', $file->mime_type);
        Storage::disk('s3')->assertExists($file->s3_key);
        $this->assertStringStartsWith('Learn/1/', $file->s3_key);
        $this->assertSame('%PDF-1.4 fake pdf bytes', Storage::disk('s3')->get($file->s3_key));
    }

    public function test_upload_rejects_mime_types_outside_the_document_allowlist(): void
    {
        $service = app(CourseFileService::class);

        // text/html and image/svg+xml can execute script when served inline
        // on the same origin — stored XSS via a "course file" upload.
        foreach (['text/html', 'image/svg+xml', 'application/javascript'] as $mime) {
            $dataUri = "data:{$mime};base64," . base64_encode('<script>alert(1)</script>');

            try {
                $service->upload(1, 'evil', $dataUri);
                $this->fail("Expected upload of {$mime} to be rejected.");
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->assertArrayHasKey('file', $e->errors());
            }
        }
    }

    public function test_encode_and_decode_file_id_round_trip(): void
    {
        $service = app(CourseFileService::class);
        $s3Key = 'Learn/1/abc-def.pdf';

        $fileId = $service->encodeFileId($s3Key);

        $this->assertStringStartsWith('s3.', $fileId);
        $this->assertSame($s3Key, $service->decodeFileId($fileId));
    }

    public function test_decode_file_id_returns_null_for_non_s3_ids(): void
    {
        $service = app(CourseFileService::class);

        $this->assertNull($service->decodeFileId('legacy-drive-id-123'));
    }

    public function test_stream_response_serves_file_bytes_and_content_type(): void
    {
        $service = app(CourseFileService::class);
        $file = $service->upload(1, 'Notes.pdf', 'data:application/pdf;base64,' . base64_encode('hello'));

        $response = $service->streamResponse($file);

        $this->assertSame('hello', $response->getContent());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_stream_response_404s_when_s3_object_is_missing(): void
    {
        $file = \App\Models\Learn\File::create([
            'title' => 'Ghost.pdf', 's3_key' => 'Learn/1/does-not-exist.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 10,
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        app(CourseFileService::class)->streamResponse($file);
    }
}
