<?php

namespace Tests\Unit\FacultyLoading;

use App\Services\FacultyLoading\ResearchSubmissionFileService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ResearchSubmissionFileServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
    }

    private function dataUri(string $mime, string $content): string
    {
        return "data:{$mime};base64," . base64_encode($content);
    }

    public function test_stores_a_valid_pdf_and_returns_metadata(): void
    {
        $service = new ResearchSubmissionFileService();
        $result  = $service->decodeAndStore($this->dataUri('application/pdf', '%PDF-1.4 fake content'), 'chapter1.pdf');

        $this->assertSame('application/pdf', $result['mime_type']);
        $this->assertSame('chapter1.pdf', $result['original_filename']);
        Storage::disk('s3')->assertExists($result['s3_key']);
    }

    public function test_rejects_disallowed_mime_type(): void
    {
        $service = new ResearchSubmissionFileService();
        $this->expectException(ValidationException::class);
        $service->decodeAndStore($this->dataUri('application/x-msdownload', 'MZ...'), 'virus.exe');
    }

    public function test_rejects_file_over_size_cap(): void
    {
        $service = new ResearchSubmissionFileService();
        $big = str_repeat('a', ResearchSubmissionFileService::MAX_BYTES + 1);
        $this->expectException(ValidationException::class);
        $service->decodeAndStore($this->dataUri('application/pdf', $big), 'big.pdf');
    }

    public function test_rejects_malformed_data_uri(): void
    {
        $service = new ResearchSubmissionFileService();
        $this->expectException(ValidationException::class);
        $service->decodeAndStore('not-a-data-uri', 'x.pdf');
    }

    public function test_enforces_requirement_specific_accepted_types(): void
    {
        $service = new ResearchSubmissionFileService();
        $this->expectException(ValidationException::class);
        // pdf is globally allowed, but this requirement only accepts docx.
        $service->decodeAndStore($this->dataUri('application/pdf', 'content'), 'x.pdf', 'docx');
    }

    public function test_encode_decode_key_roundtrip(): void
    {
        $service = new ResearchSubmissionFileService();
        $encoded = $service->encodeKey('research-requirements/abc123.pdf');
        $this->assertStringStartsWith('s3.', $encoded);
        $this->assertSame('research-requirements/abc123.pdf', $service->decodeKey($encoded));
    }
}
