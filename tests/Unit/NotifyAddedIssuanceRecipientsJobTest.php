<?php

namespace Tests\Unit;

use App\Jobs\NotifyAddedIssuanceRecipients;
use App\Mail\IssuanceReleasedMail;
use App\Models\Issuance;
use App\Models\IssuanceRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotifyAddedIssuanceRecipientsJobTest extends TestCase
{
    use RefreshDatabase;

    private function releasedIssuance(): Issuance
    {
        $creator = User::factory()->create();

        return Issuance::create([
            'type' => 'MEMO',
            'control_number' => 'MEMO-2026-08-' . uniqid(),
            'series_no' => 1,
            'year' => 2026,
            'month' => 8,
            'title' => 'Test Memo',
            'recipient_type' => 'individual',
            'status' => 'released',
            'released_at' => now(),
            'created_by' => $creator->id,
        ]);
    }

    public function test_it_emails_and_notifies_each_targeted_recipient(): void
    {
        Mail::fake();

        $issuance = $this->releasedIssuance();
        $user = User::factory()->create();
        $recipient = IssuanceRecipient::create(['issuance_id' => $issuance->id, 'user_id' => $user->id]);

        (new NotifyAddedIssuanceRecipients($issuance->id, [$recipient->id]))->handle();

        Mail::assertSent(IssuanceReleasedMail::class, fn ($mail) => $mail->issuance->is($issuance) && $mail->recipientName === $user->name);
        $this->assertSame('sent', $recipient->fresh()->email_status);
        $this->assertNotNull($recipient->fresh()->emailed_at);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id]);
    }

    public function test_it_marks_skipped_when_recipient_has_no_email(): void
    {
        Mail::fake();

        $issuance = $this->releasedIssuance();
        $user = User::factory()->create(['email' => '']);
        $recipient = IssuanceRecipient::create(['issuance_id' => $issuance->id, 'user_id' => $user->id]);

        (new NotifyAddedIssuanceRecipients($issuance->id, [$recipient->id]))->handle();

        $this->assertSame('skipped', $recipient->fresh()->email_status);
        Mail::assertNotSent(IssuanceReleasedMail::class);
    }

    public function test_it_only_touches_the_requested_recipient_ids(): void
    {
        Mail::fake();

        $issuance = $this->releasedIssuance();
        $targeted = IssuanceRecipient::create(['issuance_id' => $issuance->id, 'user_id' => User::factory()->create()->id]);
        $untouched = IssuanceRecipient::create(['issuance_id' => $issuance->id, 'user_id' => User::factory()->create()->id]);

        (new NotifyAddedIssuanceRecipients($issuance->id, [$targeted->id]))->handle();

        $this->assertSame('sent', $targeted->fresh()->email_status);
        $this->assertSame('pending', $untouched->fresh()->email_status);
    }
}
