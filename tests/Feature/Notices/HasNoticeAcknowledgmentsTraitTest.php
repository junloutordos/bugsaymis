<?php

namespace Tests\Feature\Notices;

use App\Models\Administration\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasNoticeAcknowledgmentsTraitTest extends TestCase
{
    use RefreshDatabase;

    public function test_acknowledge_for_creates_row_and_is_acknowledged_by_reflects_it(): void
    {
        $announcement = Announcement::create([
            'title' => 'Test', 'body' => 'Body', 'audience' => 'all',
            'status' => 'published', 'published_at' => now(), 'created_by' => User::factory()->create()->id,
        ]);
        $user = User::factory()->create();

        $this->assertFalse($announcement->isAcknowledgedBy($user));

        $announcement->acknowledgeFor($user);

        $this->assertTrue($announcement->fresh()->isAcknowledgedBy($user));
        $this->assertDatabaseHas('notice_acknowledgments', [
            'notice_type' => Announcement::class, 'notice_id' => $announcement->id,
            'recipient_type' => User::class, 'recipient_id' => $user->id,
        ]);
    }

    public function test_acknowledge_for_is_idempotent(): void
    {
        $announcement = Announcement::create([
            'title' => 'Test', 'body' => 'Body', 'audience' => 'all',
            'status' => 'published', 'published_at' => now(), 'created_by' => User::factory()->create()->id,
        ]);
        $user = User::factory()->create();

        $announcement->acknowledgeFor($user);
        $announcement->acknowledgeFor($user); // must not throw a unique-constraint violation

        $this->assertSame(1, $announcement->acknowledgments()->count());
    }

    public function test_two_different_recipient_types_acknowledging_the_same_notice_do_not_collide(): void
    {
        $announcement = Announcement::create([
            'title' => 'Test', 'body' => 'Body', 'audience' => 'all',
            'status' => 'published', 'published_at' => now(), 'created_by' => User::factory()->create()->id,
        ]);
        $user = User::factory()->create(['id' => 5001]);
        $studentId = \Illuminate\Support\Facades\DB::table('students')->insertGetId([
            'pisaysystemID' => 'TEST-ACK-1', 'firstname' => 'Ack', 'lastname' => 'Student',
        ]);
        $student = \App\Models\Student::find($studentId);

        $announcement->acknowledgeFor($user);
        $announcement->acknowledgeFor($student);

        $this->assertTrue($announcement->fresh()->isAcknowledgedBy($user));
        $this->assertTrue($announcement->fresh()->isAcknowledgedBy($student));
        $this->assertSame(2, $announcement->acknowledgments()->count());
    }
}
