<?php

namespace Tests\Feature\Notices;

use App\Jobs\NotifyAnnouncementJob;
use App\Models\Administration\Announcement;
use App\Models\StudentAttendance\ParentContact;
use App\Models\StudentCredential;
use App\Models\User;
use App\Services\StudentAttendance\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotifyAnnouncementJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_employees_audience_still_notifies_via_the_existing_bell_unchanged(): void
    {
        Notification::fake();

        $employee = User::factory()->create(['account_type' => 'employee', 'status' => 'active']);
        $announcement = Announcement::create([
            'title' => 'Staff Notice', 'body' => 'Body', 'audience' => 'employees',
            'status' => 'published', 'published_at' => now(), 'created_by' => User::factory()->create()->id,
        ]);

        (new NotifyAnnouncementJob($announcement->id))->handle(
            app(\App\Services\NoticeAudienceResolver::class),
            app(FcmService::class),
        );

        Notification::assertSentTo($employee, \App\Notifications\RequestStatusNotification::class);
    }

    public function test_students_audience_pushes_via_fcm_to_students_with_a_token(): void
    {
        $studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'TEST-JOB-1', 'firstname' => 'Job', 'lastname' => 'Student',
        ]);
        StudentCredential::create([
            'student_id' => $studentId, 'email' => 'job-student@example.com',
            'password' => bcrypt('x'), 'status' => 'active', 'email_verified_at' => now(),
            'fcm_device_token' => 'student-device-token',
        ]);
        $announcement = Announcement::create([
            'title' => 'Student Notice', 'body' => 'Body', 'audience' => 'students',
            'status' => 'published', 'published_at' => now(), 'created_by' => User::factory()->create()->id,
        ]);

        $fcm = $this->mock(FcmService::class);
        $fcm->shouldReceive('send')
            ->once()
            ->with('student-device-token', 'New Announcement', 'Student Notice', [
                'type' => 'announcement', 'announcement_id' => (string) $announcement->id,
            ])
            ->andReturn(true);

        (new NotifyAnnouncementJob($announcement->id))->handle(
            app(\App\Services\NoticeAudienceResolver::class),
            $fcm,
        );
    }

    public function test_parents_audience_pushes_only_to_parents_who_want_push(): void
    {
        $wantsPush = ParentContact::create([
            'name' => 'Wants Push', 'email' => 'wants@example.com', 'password' => bcrypt('x'),
            'status' => 'active', 'notify_push' => true, 'fcm_device_token' => 'parent-token',
        ]);
        ParentContact::create([
            'name' => 'No Push', 'email' => 'nopush@example.com', 'password' => bcrypt('x'),
            'status' => 'active', 'notify_push' => false, 'fcm_device_token' => 'parent-token-2',
        ]);
        $announcement = Announcement::create([
            'title' => 'Parent Notice', 'body' => 'Body', 'audience' => 'parents',
            'status' => 'published', 'published_at' => now(), 'created_by' => User::factory()->create()->id,
        ]);

        $fcm = $this->mock(FcmService::class);
        $fcm->shouldReceive('send')->once()->with('parent-token', 'New Announcement', 'Parent Notice', [
            'type' => 'announcement', 'announcement_id' => (string) $announcement->id,
        ])->andReturn(true);

        (new NotifyAnnouncementJob($announcement->id))->handle(
            app(\App\Services\NoticeAudienceResolver::class),
            $fcm,
        );
    }
}
