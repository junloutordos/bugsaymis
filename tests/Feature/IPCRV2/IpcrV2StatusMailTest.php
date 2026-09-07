<?php

namespace Tests\Feature\IPCRV2;

use App\Mail\IpcrV2StatusMail;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcrV2StatusMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_renders_with_status_and_remarks(): void
    {
        $employee = User::factory()->create(['name' => 'Jane Faculty']);
        $recipient = User::factory()->create(['name' => 'John Chief']);
        $period = IPCRRatingPeriod::create(['label' => 'SY 2026-2027', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
        $record = IpcrV2Record::create(['user_id' => $employee->id, 'rating_period_id' => $period->id]);
        $record->load('user', 'period');

        $mail = new IpcrV2StatusMail($record, $recipient, 'For Review', 'Please review promptly.');
        $rendered = $mail->render();

        $this->assertStringContainsString('John Chief', $rendered);
        $this->assertStringContainsString('For Review', $rendered);
        $this->assertStringContainsString('Please review promptly.', $rendered);
    }
}
