<?php

namespace Tests\Feature\Notices;

use App\Models\Administration\Announcement;
use App\Models\User;
use App\Services\PersonalDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalDashboardAnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_announcements_include_is_read_flag(): void
    {
        $user = User::factory()->create(['account_type' => 'employee']);
        $unread = Announcement::create([
            'title' => 'Unread', 'body' => 'Body', 'audience' => 'all',
            'status' => 'published', 'published_at' => now(), 'created_by' => $user->id,
        ]);
        $read = Announcement::create([
            'title' => 'Read', 'body' => 'Body', 'audience' => 'all',
            'status' => 'published', 'published_at' => now(), 'created_by' => $user->id,
        ]);
        $read->acknowledgeFor($user);

        $data = app(PersonalDashboardService::class)->payload($user);

        $byTitle = collect($data['announcements'])->keyBy('title');
        $this->assertFalse($byTitle['Unread']['is_read']);
        $this->assertTrue($byTitle['Read']['is_read']);
    }
}
