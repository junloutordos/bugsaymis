<?php

namespace Tests\Unit\ALP;

use App\Services\ALP\AlpFileService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AlpFileServiceTest extends TestCase
{
    public function test_it_stores_valid_base64_pdf_on_private_s3_path(): void
    {
        Storage::fake('s3');
        $service = app(AlpFileService::class);

        $fileId = $service->uploadDataUri('2026/1/consents', 'data:application/pdf;base64,'.base64_encode('%PDF-1.4 test'));
        $key = $service->decode($fileId);

        $this->assertStringStartsWith('s3.', $fileId);
        $this->assertStringStartsWith('ALP/2026/1/consents/', $key);
        Storage::disk('s3')->assertExists($key);
    }

    public function test_it_rejects_a_file_whose_content_does_not_match_the_declared_type(): void
    {
        Storage::fake('s3');
        $this->expectException(ValidationException::class);

        app(AlpFileService::class)->uploadDataUri(
            '2026/1/consents',
            'data:application/pdf;base64,'.base64_encode('<script>alert(1)</script>'),
        );
    }

    public function test_file_identifiers_round_trip_without_exposing_the_s3_key(): void
    {
        $service = app(AlpFileService::class);
        $key = 'ALP/2026/1/consents/consent.pdf';

        $this->assertSame($key, $service->decode($service->encode($key)));
        $this->assertNull($service->decode('not-an-s3-identifier'));
    }
}
