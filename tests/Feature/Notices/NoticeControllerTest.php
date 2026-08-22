<?php

namespace Tests\Feature\Notices;

use App\Models\Administration\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoticeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_excludes_already_acknowledged_and_unpublished_announcements(): void
    {
        $user = User::factory()->create(['account_type' => 'employee']);
        $unread = Announcement::create([
            'title' => 'Unread', 'body' => 'Body', 'audience' => 'all',
            'status' => 'published', 'published_at' => now(), 'created_by' => $user->id,
        ]);
        $alreadyRead = Announcement::create([
            'title' => 'Already Read', 'body' => 'Body', 'audience' => 'all',
            'status' => 'published', 'published_at' => now(), 'created_by' => $user->id,
        ]);
        $alreadyRead->acknowledgeFor($user);
        Announcement::create([
            'title' => 'Draft', 'body' => 'Body', 'audience' => 'all',
            'status' => 'draft', 'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson('/notices/pending');

        $response->assertOk();
        $titles = collect($response->json('announcements'))->pluck('title');
        $this->assertTrue($titles->contains('Unread'));
        $this->assertFalse($titles->contains('Already Read'));
        $this->assertFalse($titles->contains('Draft'));
    }

    public function test_acknowledge_writes_a_notice_acknowledgment_row(): void
    {
        $user = User::factory()->create();
        $announcement = Announcement::create([
            'title' => 'To Ack', 'body' => 'Body', 'audience' => 'all',
            'status' => 'published', 'published_at' => now(), 'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/notices/announcement/{$announcement->id}/acknowledge");

        $response->assertOk();
        $this->assertTrue($announcement->fresh()->isAcknowledgedBy($user));
    }

    public function test_pending_requires_authentication(): void
    {
        $this->getJson('/notices/pending')->assertUnauthorized();
    }

    public function test_acknowledge_rejects_an_announcement_not_addressed_to_the_user(): void
    {
        $creator = User::factory()->create();
        $targetedUser = User::factory()->create();
        $outsider = User::factory()->create();
        $announcement = Announcement::create([
            'title' => 'Specific', 'body' => 'Body', 'audience' => 'specific',
            'status' => 'published', 'published_at' => now(), 'created_by' => $creator->id,
        ]);
        $announcement->targets()->sync([$targetedUser->id]);

        $this->actingAs($outsider)
            ->postJson("/notices/announcement/{$announcement->id}/acknowledge")
            ->assertNotFound();

        $this->assertFalse($announcement->fresh()->isAcknowledgedBy($outsider));
    }
}
