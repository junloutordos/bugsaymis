<?php

namespace Tests\Feature\SPMS;

use App\Models\SPMS\IpcrTarget;
use App\Models\User;
use App\Services\SPMS\MovChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MovChecklistServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploads_base64_evidence_to_s3_and_marks_submitted(): void
    {
        Storage::fake('s3');
        $target = IpcrTarget::factory()->create();
        $user = User::factory()->create();
        $tinyPngBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

        $item = (new MovChecklistService())->uploadEvidence($target, 'SIP', $tinyPngBase64, $user->id);

        $this->assertSame('submitted', $item->status);
        $this->assertNotNull($item->s3_key);
        $this->assertSame($user->id, $item->submitted_by);
        Storage::disk('s3')->assertExists($item->s3_key);
    }
}
