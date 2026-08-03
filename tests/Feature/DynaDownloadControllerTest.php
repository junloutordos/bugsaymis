<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DynaDownloadControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_appcast_serves_the_feed_xml_from_s3(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('dyna-releases/appcast.xml', '<rss version="2.0"></rss>');

        $response = $this->get('/downloads/dyna/appcast.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml');
        $this->assertEquals('<rss version="2.0"></rss>', $response->getContent());
    }

    public function test_appcast_404s_when_no_feed_has_been_published_yet(): void
    {
        Storage::fake('s3');

        $this->get('/downloads/dyna/appcast.xml')->assertNotFound();
    }

    public function test_download_redirects_to_a_presigned_url_for_a_valid_dmg_filename(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('dyna-releases/Dyna-1.0.0.dmg', UploadedFile::fake()->create('Dyna-1.0.0.dmg', 10));

        $response = $this->get('/downloads/dyna/download/Dyna-1.0.0.dmg');

        $response->assertRedirect();
        $this->assertNotEmpty($response->headers->get('Location'));
    }

    public function test_download_404s_for_a_filename_that_does_not_match_the_expected_pattern(): void
    {
        Storage::fake('s3');

        $this->get('/downloads/dyna/download/'.urlencode('../../etc/passwd'))->assertNotFound();
    }

    public function test_download_404s_when_the_dmg_does_not_exist_in_s3(): void
    {
        Storage::fake('s3');

        $this->get('/downloads/dyna/download/Dyna-9.9.9.dmg')->assertNotFound();
    }
}
