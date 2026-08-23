<?php

namespace Tests\Feature\Mobile;

use App\Models\Administration\Announcement;
use App\Models\Student;
use App\Models\StudentAttendance\ParentContact;
use App\Models\StudentCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MobileNoticeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Keepsuit\LaravelOpenTelemetry::collectUserContext() type-hints
        // Illuminate\Contracts\Auth\Authenticatable and fatals under
        // PHPUnit's HTTP-kernel path for a ParentContact-authenticated
        // request — confirmed elsewhere in this codebase that this never
        // fires on real traffic (test-environment-only quirk).
        config(['opentelemetry.user_context' => false]);
    }

    public function test_student_pending_returns_announcements_for_all_and_students_audiences(): void
    {
        $studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'TEST-MOB-1', 'firstname' => 'Mob', 'lastname' => 'Student',
        ]);
        StudentCredential::create([
            'student_id' => $studentId, 'email' => 'mobnotice@example.com',
            'password' => bcrypt('x'), 'status' => 'active', 'email_verified_at' => now(),
        ]);
        $student = Student::find($studentId);
        $token = $student->createToken('device', ['mobile'])->plainTextToken;

        $creator = User::factory()->create();
        Announcement::create([
            'title' => 'For Students', 'body' => 'Body', 'audience' => 'students',
            'status' => 'published', 'published_at' => now(), 'created_by' => $creator->id,
        ]);
        Announcement::create([
            'title' => 'For Parents', 'body' => 'Body', 'audience' => 'parents',
            'status' => 'published', 'published_at' => now(), 'created_by' => $creator->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/mobile/notices/pending');

        $response->assertOk();
        $titles = collect($response->json('announcements'))->pluck('title');
        $this->assertTrue($titles->contains('For Students'));
        $this->assertFalse($titles->contains('For Parents'));
    }

    public function test_parent_acknowledge_marks_it_read_and_it_drops_out_of_pending(): void
    {
        $parent = ParentContact::create([
            'name' => 'Mob Parent', 'email' => 'mobparent@example.com', 'password' => bcrypt('x'), 'status' => 'active',
        ]);
        $token = $parent->createToken('device', ['mobile'])->plainTextToken;
        $announcement = Announcement::create([
            'title' => 'For Parents', 'body' => 'Body', 'audience' => 'parents',
            'status' => 'published', 'published_at' => now(), 'created_by' => User::factory()->create()->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/mobile/notices/announcement/{$announcement->id}/acknowledge")
            ->assertOk();

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/mobile/notices/pending');
        $this->assertEmpty($response->json('announcements'));
    }

    public function test_parent_cannot_acknowledge_an_announcement_not_addressed_to_parents(): void
    {
        $parent = ParentContact::create([
            'name' => 'Mob Parent', 'email' => 'mobparent2@example.com', 'password' => bcrypt('x'), 'status' => 'active',
        ]);
        $token = $parent->createToken('device', ['mobile'])->plainTextToken;
        $announcement = Announcement::create([
            'title' => 'For Students Only', 'body' => 'Body', 'audience' => 'students',
            'status' => 'published', 'published_at' => now(), 'created_by' => User::factory()->create()->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/mobile/notices/announcement/{$announcement->id}/acknowledge")
            ->assertNotFound();

        $this->assertFalse($announcement->fresh()->isAcknowledgedBy($parent));
    }

    public function test_history_returns_all_published_announcements_for_the_audience_group_with_read_flag(): void
    {
        $studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'TEST-HIST-1', 'firstname' => 'Hist', 'lastname' => 'Student',
        ]);
        StudentCredential::create([
            'student_id' => $studentId, 'email' => 'histstudent@example.com',
            'password' => bcrypt('x'), 'status' => 'active', 'email_verified_at' => now(),
        ]);
        $student = Student::find($studentId);
        $token = $student->createToken('device', ['mobile'])->plainTextToken;

        $creator = User::factory()->create();
        $read = Announcement::create([
            'title' => 'Already Read', 'body' => 'Body', 'audience' => 'students',
            'status' => 'published', 'published_at' => now()->subDay(), 'created_by' => $creator->id,
        ]);
        Announcement::create([
            'title' => 'Still Unread', 'body' => 'Body', 'audience' => 'students',
            'status' => 'published', 'published_at' => now(), 'created_by' => $creator->id,
        ]);
        Announcement::create([
            'title' => 'For Parents', 'body' => 'Body', 'audience' => 'parents',
            'status' => 'published', 'published_at' => now(), 'created_by' => $creator->id,
        ]);
        $read->acknowledgeFor($student);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/mobile/notices/history');

        $response->assertOk();
        $items = collect($response->json('data'));
        $this->assertCount(2, $items);
        $this->assertTrue($items->firstWhere('title', 'Already Read')['is_read']);
        $this->assertFalse($items->firstWhere('title', 'Still Unread')['is_read']);
        $this->assertNull($items->firstWhere('title', 'For Parents'));
    }

    public function test_history_is_paginated_at_15_per_page(): void
    {
        $studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'TEST-HIST-2', 'firstname' => 'Page', 'lastname' => 'Student',
        ]);
        StudentCredential::create([
            'student_id' => $studentId, 'email' => 'pagestudent@example.com',
            'password' => bcrypt('x'), 'status' => 'active', 'email_verified_at' => now(),
        ]);
        $token = Student::find($studentId)->createToken('device', ['mobile'])->plainTextToken;

        $creator = User::factory()->create();
        for ($i = 0; $i < 20; $i++) {
            Announcement::create([
                'title' => "Announcement {$i}", 'body' => 'Body', 'audience' => 'students',
                'status' => 'published', 'published_at' => now()->subMinutes($i), 'created_by' => $creator->id,
            ]);
        }

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/mobile/notices/history');

        $response->assertOk();
        $this->assertCount(15, $response->json('data'));
        $this->assertSame(20, $response->json('total'));
    }
}
