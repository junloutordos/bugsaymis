<?php

namespace Tests\Feature\AMS;

use App\Jobs\AMS\GenerateActivityCertificates;
use App\Models\AMS\Activity;
use App\Models\AMS\ActivityEvaluation;
use App\Models\AMS\ActivityParticipant;
use App\Models\AMS\ActivityTwsEvaluation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AMS\CertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CertificateEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_attendance_never_generates_or_emails_certificate(): void
    {
        [$owner, $attendee, $activity, $participant] = $this->employeeParticipant();
        $certificate = $this->mock(CertificateService::class);
        $certificate->shouldNotReceive('buildAndSave');
        $certificate->shouldNotReceive('sendCertificateEmail');

        $this->actingAs($owner)
            ->post(route('ams.activities.participants.save-attendance', [$activity, $participant]), [
                'attended' => 'yes',
                'hours_attended' => 8,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ams_activity_participants', [
            'id' => $participant->id,
            'attended' => 'yes',
            'hours_attended' => 8,
            'certificate_path' => null,
        ]);
    }

    public function test_manual_generation_queues_a_background_job(): void
    {
        Queue::fake();

        [$owner, $attendee, $activity, $participant] = $this->employeeParticipant([
            'attended' => 'yes',
            'hours_attended' => 8,
        ]);

        $this->actingAs($owner)
            ->post(route('ams.activities.certificates.generate', $activity))
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(
            GenerateActivityCertificates::class,
            fn ($job) => $job->activityId === $activity->id
                && $job->requestedByUserId === $owner->id
                && $job->queue === 'bulk'
        );

        // The HTTP request itself must not touch certificate generation —
        // that all happens later, in the queued job.
        $this->assertNull($participant->fresh()->certificate_path);
    }

    public function test_generation_job_skips_present_participant_without_evaluation(): void
    {
        [$owner, $attendee, $activity, $participant] = $this->employeeParticipant([
            'attended' => 'yes',
            'hours_attended' => 8,
        ]);
        $certificate = $this->mock(CertificateService::class);
        $certificate->shouldNotReceive('buildAndSave');
        $certificate->shouldNotReceive('sendCertificateEmail');

        (new GenerateActivityCertificates($activity->id, $owner->id))->handle(
            app(CertificateService::class),
            app(\App\Services\AMS\ActivityEvaluationEligibilityService::class),
        );

        $this->assertNull($participant->fresh()->certificate_path);
    }

    public function test_generation_job_creates_and_emails_certificate_after_in_house_evaluation(): void
    {
        [$owner, $attendee, $activity, $participant] = $this->employeeParticipant([
            'attended' => 'yes',
            'hours_attended' => 8,
        ]);
        ActivityEvaluation::create([
            'activity_id' => $activity->id,
            'participant_type' => 'employee',
            'participant_id' => $attendee->id,
        ]);

        $certificate = $this->mock(CertificateService::class);
        $certificate->shouldReceive('buildAndSave')->once()->andReturn('ams/certificates/eligible.pdf');
        $certificate->shouldReceive('sendCertificateEmail')->once();

        (new GenerateActivityCertificates($activity->id, $owner->id))->handle(
            app(CertificateService::class),
            app(\App\Services\AMS\ActivityEvaluationEligibilityService::class),
        );

        $this->assertSame('ams/certificates/eligible.pdf', $participant->fresh()->certificate_path);
    }

    public function test_generation_job_continues_and_reports_failed_participant_name_when_build_throws(): void
    {
        [$owner, $attendee1, $activity, $participant1] = $this->employeeParticipant([
            'attended' => 'yes',
            'hours_attended' => 8,
        ]);
        ActivityEvaluation::create([
            'activity_id' => $activity->id,
            'participant_type' => 'employee',
            'participant_id' => $attendee1->id,
        ]);

        $attendee2 = User::factory()->create(['email' => uniqid().'@example.test']);
        $participant2 = ActivityParticipant::create([
            'activity_id' => $activity->id,
            'participant_id' => $attendee2->id,
            'participant_type' => 'employee',
            'attended' => 'yes',
            'hours_attended' => 8,
        ]);
        ActivityEvaluation::create([
            'activity_id' => $activity->id,
            'participant_type' => 'employee',
            'participant_id' => $attendee2->id,
        ]);

        $certificate = $this->mock(CertificateService::class);
        $certificate->shouldReceive('buildAndSave')
            ->twice()
            ->andReturnUsing(function (...$args) use ($attendee1) {
                if ($args[1] === $attendee1->name) {
                    throw new \RuntimeException('PDF generation failed: simulated mPDF error');
                }
                return 'ams/certificates/ok.pdf';
            });
        $certificate->shouldReceive('sendCertificateEmail')->once();

        (new GenerateActivityCertificates($activity->id, $owner->id))->handle(
            app(CertificateService::class),
            app(\App\Services\AMS\ActivityEvaluationEligibilityService::class),
        );

        $this->assertNull($participant1->fresh()->certificate_path);
        $this->assertSame('ams/certificates/ok.pdf', $participant2->fresh()->certificate_path);
    }

    public function test_generation_job_handles_participants_with_duplicate_names_independently(): void
    {
        [$owner, $attendee1, $activity, $participant1] = $this->employeeParticipant([
            'attended' => 'yes',
            'hours_attended' => 8,
        ]);
        $attendee1->update(['name' => 'Juan Dela Cruz']);
        ActivityEvaluation::create([
            'activity_id' => $activity->id,
            'participant_type' => 'employee',
            'participant_id' => $attendee1->id,
        ]);

        // Second participant with the exact same name — must not collide on storage path.
        $attendee2 = User::factory()->create([
            'name' => 'Juan Dela Cruz',
            'email' => uniqid().'@example.test',
        ]);
        $participant2 = ActivityParticipant::create([
            'activity_id' => $activity->id,
            'participant_id' => $attendee2->id,
            'participant_type' => 'employee',
            'attended' => 'yes',
            'hours_attended' => 8,
        ]);
        ActivityEvaluation::create([
            'activity_id' => $activity->id,
            'participant_type' => 'employee',
            'participant_id' => $attendee2->id,
        ]);

        // Use the real CertificateService to verify the actual generated storage paths differ.
        \Illuminate\Support\Facades\Storage::fake('s3');
        $realService = app(CertificateService::class);
        $path1 = $realService->buildAndSave($activity, $attendee1->name, 8, $attendee1->id, 'employee');
        $path2 = $realService->buildAndSave($activity, $attendee2->name, 8, $attendee2->id, 'employee');

        $this->assertNotSame($path1, $path2, 'Certificates for participants sharing a name must not collide on storage path.');
        $this->assertStringContainsString((string) $attendee1->id, $path1);
        $this->assertStringContainsString((string) $attendee2->id, $path2);
    }

    public function test_training_evaluation_qualifies_participant_for_certificate(): void
    {
        [$owner, $attendee, $activity, $participant] = $this->employeeParticipant([
            'attended' => 'yes',
            'hours_attended' => 8,
        ], Activity::TYPE_TRAINING_WORKSHOP_SEMINAR);
        ActivityTwsEvaluation::create([
            'activity_id' => $activity->id,
            'participant_type' => 'employee',
            'participant_id' => $attendee->id,
        ]);

        $certificate = $this->mock(CertificateService::class);
        $certificate->shouldReceive('buildAndSave')->once()->andReturn('ams/certificates/tws.pdf');
        $certificate->shouldReceive('sendCertificateEmail')->once();

        (new GenerateActivityCertificates($activity->id, $owner->id))->handle(
            app(CertificateService::class),
            app(\App\Services\AMS\ActivityEvaluationEligibilityService::class),
        );

        $this->assertSame('ams/certificates/tws.pdf', $participant->fresh()->certificate_path);
    }

    public function test_attendance_change_invalidates_existing_certificate(): void
    {
        [$owner, $attendee, $activity, $participant] = $this->employeeParticipant([
            'attended' => 'yes',
            'hours_attended' => 8,
            'certificate_path' => 'ams/certificates/old.pdf',
        ]);
        ActivityEvaluation::create([
            'activity_id' => $activity->id,
            'participant_type' => 'employee',
            'participant_id' => $attendee->id,
        ]);

        $certificate = $this->mock(CertificateService::class);
        $certificate->shouldReceive('delete')->once()->with('ams/certificates/old.pdf');
        $certificate->shouldNotReceive('buildAndSave');
        $certificate->shouldNotReceive('sendCertificateEmail');

        $this->actingAs($owner)
            ->post(route('ams.activities.participants.save-attendance', [$activity, $participant]), [
                'attended' => 'yes',
                'hours_attended' => 6,
            ])
            ->assertRedirect();

        $this->assertNull($participant->fresh()->certificate_path);
        $this->assertSame('6.00', $participant->fresh()->hours_attended);
    }

    public function test_premature_certificate_cannot_be_downloaded_or_publicly_verified(): void
    {
        [$owner, $attendee, $activity, $participant] = $this->employeeParticipant([
            'attended' => 'yes',
            'hours_attended' => 8,
            'certificate_path' => 'ams/certificates/premature.pdf',
        ]);

        $this->actingAs($owner)
            ->get(route('ams.activities.certificates.download.participant', [$activity, $participant]))
            ->assertForbidden();

        $this->get(route('ams.certificates.verify', [
            $activity,
            md5($attendee->id.'-'.$activity->id),
        ]))->assertNotFound();
    }

    private function employeeParticipant(array $participantOverrides = [], string $activityType = Activity::TYPE_IN_HOUSE): array
    {
        $owner = $this->userWithPermission('activities.manage');
        $attendee = User::factory()->create(['email' => uniqid().'@example.test']);
        $activity = Activity::create([
            'user_id' => $owner->id,
            'title' => 'Eligibility Test Activity',
            'activity_type' => $activityType,
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
        $role = Role::create(['name' => 'AMS Certificate Test '.uniqid()]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        return $user;
    }
}
