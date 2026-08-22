<?php

namespace Tests\Feature\Notices;

use App\Models\Administration\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementAudienceScopesTest extends TestCase
{
    use RefreshDatabase;

    private function makeAnnouncement(string $audience): Announcement
    {
        return Announcement::create([
            'title' => "Audience {$audience}", 'body' => 'Body', 'audience' => $audience,
            'status' => 'published', 'published_at' => now(), 'created_by' => User::factory()->create()->id,
        ]);
    }

    public function test_visible_to_user_matches_all_and_employees_but_not_students_or_parents(): void
    {
        $all       = $this->makeAnnouncement('all');
        $employees = $this->makeAnnouncement('employees');
        $students  = $this->makeAnnouncement('students');
        $parents   = $this->makeAnnouncement('parents');
        $user      = User::factory()->create();

        $ids = Announcement::visibleTo($user)->pluck('id');

        $this->assertTrue($ids->contains($all->id));
        $this->assertTrue($ids->contains($employees->id));
        $this->assertFalse($ids->contains($students->id));
        $this->assertFalse($ids->contains($parents->id));
    }

    public function test_visible_to_audience_group_matches_all_and_the_named_group_only(): void
    {
        $all      = $this->makeAnnouncement('all');
        $students = $this->makeAnnouncement('students');
        $parents  = $this->makeAnnouncement('parents');

        $ids = Announcement::visibleToAudienceGroup('students')->pluck('id');

        $this->assertTrue($ids->contains($all->id));
        $this->assertTrue($ids->contains($students->id));
        $this->assertFalse($ids->contains($parents->id));
    }

    public function test_draft_announcements_are_never_visible_regardless_of_audience(): void
    {
        Announcement::create([
            'title' => 'Draft', 'body' => 'Body', 'audience' => 'all',
            'status' => 'draft', 'created_by' => User::factory()->create()->id,
        ]);

        $this->assertCount(0, Announcement::visibleToAudienceGroup('students')->get());
    }
}
