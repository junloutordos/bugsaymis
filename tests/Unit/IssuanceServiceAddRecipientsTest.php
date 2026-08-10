<?php

namespace Tests\Unit;

use App\Models\Division;
use App\Models\Issuance;
use App\Models\IssuanceRecipient;
use App\Models\Office;
use App\Models\User;
use App\Services\IssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssuanceServiceAddRecipientsTest extends TestCase
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

    public function test_it_adds_new_individual_recipients_and_returns_their_ids(): void
    {
        $issuance = $this->releasedIssuance();
        $user = User::factory()->create();

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'recipient_type' => 'individual',
            'user_ids' => [$user->id],
        ]);

        $this->assertCount(1, $newIds);
        $this->assertDatabaseHas('issuance_recipients', [
            'issuance_id' => $issuance->id,
            'user_id' => $user->id,
        ]);
        $recipient = IssuanceRecipient::find($newIds[0]);
        $this->assertNotNull($recipient->notified_at);
    }

    public function test_it_skips_users_who_are_already_recipients(): void
    {
        $issuance = $this->releasedIssuance();
        $existing = User::factory()->create();
        $new = User::factory()->create();
        IssuanceRecipient::create(['issuance_id' => $issuance->id, 'user_id' => $existing->id]);

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'recipient_type' => 'individual',
            'user_ids' => [$existing->id, $new->id],
        ]);

        $this->assertCount(1, $newIds);
        $this->assertSame($new->id, IssuanceRecipient::find($newIds[0])->user_id);
        $this->assertSame(2, $issuance->recipients()->count());
    }

    public function test_it_returns_empty_array_when_everyone_selected_is_already_a_recipient(): void
    {
        $issuance = $this->releasedIssuance();
        $existing = User::factory()->create();
        IssuanceRecipient::create(['issuance_id' => $issuance->id, 'user_id' => $existing->id]);

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'recipient_type' => 'individual',
            'user_ids' => [$existing->id],
        ]);

        $this->assertSame([], $newIds);
        $this->assertSame(1, $issuance->recipients()->count());
    }

    public function test_it_adds_recipients_by_office(): void
    {
        $issuance = $this->releasedIssuance();
        $office = Office::create(['name' => 'Test Office ' . uniqid()]);
        $memberA = User::factory()->create(['office_id' => $office->id]);
        $memberB = User::factory()->create(['office_id' => $office->id]);
        User::factory()->create(); // unrelated user, must not be added

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'recipient_type' => 'office',
            'office_ids' => [$office->id],
        ]);

        $this->assertCount(2, $newIds);
        $this->assertDatabaseHas('issuance_recipients', ['issuance_id' => $issuance->id, 'user_id' => $memberA->id]);
        $this->assertDatabaseHas('issuance_recipients', ['issuance_id' => $issuance->id, 'user_id' => $memberB->id]);
    }

    public function test_it_adds_recipients_by_division(): void
    {
        $issuance = $this->releasedIssuance();
        $division = Division::create(['division_name' => 'Test Division ' . uniqid()]);
        $member = User::factory()->create(['division_id' => $division->id]);

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'recipient_type' => 'division',
            'division_ids' => [$division->id],
        ]);

        $this->assertCount(1, $newIds);
        $this->assertSame($member->id, IssuanceRecipient::find($newIds[0])->user_id);
    }

    public function test_it_adds_all_active_employees_and_excludes_inactive_ones(): void
    {
        $issuance = $this->releasedIssuance();
        $active = User::factory()->create(['status' => 'active']);
        $inactive = User::factory()->create(['status' => 'inactive']);

        (new IssuanceService())->addRecipients($issuance, [
            'recipient_type' => 'all',
        ]);

        $this->assertDatabaseHas('issuance_recipients', ['issuance_id' => $issuance->id, 'user_id' => $active->id]);
        $this->assertDatabaseMissing('issuance_recipients', ['issuance_id' => $issuance->id, 'user_id' => $inactive->id]);
    }
}
