<?php

namespace Tests\Feature\AMS;

use App\Jobs\AMS\SendActivityEvaluationLinks;
use App\Mail\AMS\ActivityEvaluationInviteMail;
use App\Models\AMS\Activity;
use App\Models\AMS\ActivityParticipant;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendEvaluationLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_evaluation_links_queues_a_background_job(): void
    {
        Queue::fake();

        [$owner, $attendee, $activity] = $this->employeeParticipant([
            'attended' => 'yes',
        ]);

        $this->actingAs($owner)
            ->post(route('ams.activities.participants.send-evaluation-links', $activity))
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(
            SendActivityEvaluationLinks::class,
            fn ($job) => $job->activityId === $activity->id
                && $job->requestedByUserId === $owner->id
                && $job->queue === 'bulk'
        );
    }

    public function test_job_sends_invite_only_to_present_employees_with_email(): void
    {
        Mail::fake();

        [$owner, $attendeeWithEmail, $activity, $participantWithEmail] = $this->employeeParticipant([
            'attended' => 'yes',
        ]);

        $attendeeNoEmail = User::factory()->create(['email' => '']);
        ActivityParticipant::create([
            'activity_id' => $activity->id,
            'participant_id' => $attendeeNoEmail->id,
            'participant_type' => 'employee',
            'attended' => 'yes',
            'hours_attended' => 8,
        ]);

        $absentAttendee = User::factory()->create(['email' => uniqid().'@example.test']);
        ActivityParticipant::create([
            'activity_id' => $activity->id,
            'participant_id' => $absentAttendee->id,
            'participant_type' => 'employee',
            'attended' => 'no',
            'hours_attended' => 0,
        ]);

        (new SendActivityEvaluationLinks($activity->id, $owner->id))->handle();

        Mail::assertSent(ActivityEvaluationInviteMail::class, function ($mail) use ($attendeeWithEmail) {
            return $mail->hasTo($attendeeWithEmail->email);
        });
        Mail::assertSent(ActivityEvaluationInviteMail::class, 1);
    }

    private function employeeParticipant(array $participantOverrides = []): array
    {
        $owner = $this->userWithPermission('activities.manage');
        $attendee = User::factory()->create(['email' => uniqid().'@example.test']);
        $activity = Activity::create([
            'user_id' => $owner->id,
            'title' => 'Evaluation Links Test Activity',
            'activity_type' => Activity::TYPE_IN_HOUSE,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
        ]);
        $participant = ActivityParticipant::create(array_merge([
            'activity_id' => $activity->id,
            'participant_id' => $attendee->id,
            'participant_type' => 'employee',
            'attended' => 'no',
            'hours_attended' => 0,
        ], $participantOverrides));

        return [$owner, $attendee, $activity, $participant];
    }

    private function userWithPermission(string $permissionName): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['module' => 'Activities', 'description' => $permissionName],
        );
        $role = Role::create(['name' => 'AMS Eval Links Test '.uniqid()]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        return $user;
    }
}
