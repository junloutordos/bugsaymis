<?php

namespace Tests\Feature;

use App\Jobs\NotifyAddedIssuanceRecipients;
use App\Models\Division;
use App\Models\Issuance;
use App\Models\IssuanceRecipient;
use App\Models\Office;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IssuanceRecipientsAddTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'issuances.manage'],
            ['module' => 'Issuances', 'description' => 'issuances.manage'],
        );
        $role = Role::create(['name' => 'IssuanceAdminTester_' . uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function releasedIssuance(?User $creator = null): Issuance
    {
        $creator ??= User::factory()->create();

        return Issuance::create([
            'type' => 'MEMO',
            'control_number' => 'MEMO-2026-08-' . uniqid(),
            'series_no' => 1,
            'year' => 2026,
            'month' => 8,
            'title' => 'Test Memo',
            'recipient_type' => 'individual_staff',
            'status' => 'released',
            'released_at' => now(),
            'created_by' => $creator->id,
        ]);
    }

    public function test_admin_can_add_an_individual_recipient_to_a_released_issuance(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $issuance = $this->releasedIssuance();
        $newUser = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('issuances.recipients.add', $issuance->id), [
            'user_ids' => [$newUser->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('issuance_recipients', ['issuance_id' => $issuance->id, 'user_id' => $newUser->id]);
        Queue::assertPushed(NotifyAddedIssuanceRecipients::class, fn ($job) => $job->issuanceId === $issuance->id && in_array(
            IssuanceRecipient::where('issuance_id', $issuance->id)->where('user_id', $newUser->id)->value('id'),
            $job->recipientIds,
        ));
    }

    public function test_adding_recipients_to_a_draft_issuance_is_rejected(): void
    {
        $admin = $this->admin();
        $creator = User::factory()->create();
        $issuance = Issuance::create([
            'type' => 'MEMO',
            'control_number' => 'MEMO-2026-08-' . uniqid(),
            'series_no' => 1,
            'year' => 2026,
            'month' => 8,
            'title' => 'Draft Memo',
            'recipient_type' => 'individual_staff',
            'status' => 'draft',
            'created_by' => $creator->id,
        ]);
        $newUser = User::factory()->create();

        $this->actingAs($admin)->post(route('issuances.recipients.add', $issuance->id), [
            'user_ids' => [$newUser->id],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('issuance_recipients', ['issuance_id' => $issuance->id, 'user_id' => $newUser->id]);
    }

    public function test_adding_recipients_to_an_archived_issuance_is_rejected(): void
    {
        $admin = $this->admin();
        $issuance = $this->releasedIssuance();
        $issuance->update(['archived_at' => now()]);
        $newUser = User::factory()->create();

        $this->actingAs($admin)->post(route('issuances.recipients.add', $issuance->id), [
            'user_ids' => [$newUser->id],
        ])->assertStatus(422);
    }

    public function test_non_admin_cannot_add_recipients(): void
    {
        $issuance = $this->releasedIssuance();
        $staff = User::factory()->create();
        $newUser = User::factory()->create();

        $this->actingAs($staff)->post(route('issuances.recipients.add', $issuance->id), [
            'user_ids' => [$newUser->id],
        ])->assertStatus(403);
    }

    public function test_adding_an_office_only_notifies_the_newly_added_members(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $issuance = $this->releasedIssuance();
        $office = Office::create(['name' => 'Test Office ' . uniqid()]);
        $already = User::factory()->create(['office_id' => $office->id]);
        $new = User::factory()->create(['office_id' => $office->id]);
        IssuanceRecipient::create(['issuance_id' => $issuance->id, 'user_id' => $already->id]);

        $this->actingAs($admin)->post(route('issuances.recipients.add', $issuance->id), [
            'office_ids' => [$office->id],
        ])->assertRedirect();

        $newRecipientId = IssuanceRecipient::where('issuance_id', $issuance->id)->where('user_id', $new->id)->value('id');
        Queue::assertPushed(NotifyAddedIssuanceRecipients::class, fn ($job) => $job->recipientIds === [$newRecipientId]);
    }

    public function test_adding_only_already_existing_recipients_dispatches_no_job(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $issuance = $this->releasedIssuance();
        $existing = User::factory()->create();
        IssuanceRecipient::create(['issuance_id' => $issuance->id, 'user_id' => $existing->id]);

        $this->actingAs($admin)->post(route('issuances.recipients.add', $issuance->id), [
            'user_ids' => [$existing->id],
        ])->assertRedirect()->assertSessionHas('success');

        Queue::assertNotPushed(NotifyAddedIssuanceRecipients::class);
    }

    public function test_admin_can_combine_office_and_individual_in_one_request(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $issuance = $this->releasedIssuance();
        $office = Office::create(['name' => 'Combo Office ' . uniqid()]);
        $officeMember = User::factory()->create(['office_id' => $office->id]);
        $individual = User::factory()->create();

        $this->actingAs($admin)->post(route('issuances.recipients.add', $issuance->id), [
            'office_ids' => [$office->id],
            'user_ids' => [$individual->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('issuance_recipients', ['issuance_id' => $issuance->id, 'user_id' => $officeMember->id]);
        $this->assertDatabaseHas('issuance_recipients', ['issuance_id' => $issuance->id, 'user_id' => $individual->id]);
    }

    public function test_adding_recipients_rejects_an_empty_selection(): void
    {
        $admin = $this->admin();
        $issuance = $this->releasedIssuance();

        $this->actingAs($admin)->post(route('issuances.recipients.add', $issuance->id), [])
            ->assertStatus(422);
    }
}
