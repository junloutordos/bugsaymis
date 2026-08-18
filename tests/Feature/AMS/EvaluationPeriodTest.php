<?php

namespace Tests\Feature\AMS;

use App\Models\AMS\Activity;
use App\Models\AMS\ActivityCoProponent;
use App\Models\AMS\ActivityEvaluation;
use App\Models\AMS\ActivityParticipant;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluation_open_defaults_true_on_new_activity(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner);

        $this->assertTrue($activity->fresh()->evaluation_open);
        $this->assertNull($activity->fresh()->evaluation_status_changed_at);
    }

    public function test_is_multi_day_and_attendance_day_list(): void
    {
        $owner = $this->userWithPermission('activities.manage');

        $singleDay = $this->makeActivity($owner, [
            'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
        ]);
        $this->assertFalse($singleDay->isMultiDay());
        $this->assertSame([], $singleDay->attendanceDayList());

        $multiDay = $this->makeActivity($owner, [
            'start_date' => '2026-08-10', 'end_date' => '2026-08-12',
        ]);
        $this->assertTrue($multiDay->isMultiDay());
        $this->assertSame(
            ['2026-08-10', '2026-08-11', '2026-08-12'],
            $multiDay->attendanceDayList()
        );
    }

    public function test_owner_can_close_and_reopen_evaluation_period(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner);

        $this->actingAs($owner)
            ->post(route('ams.activities.evaluation-period.toggle', $activity), ['open' => false])
            ->assertRedirect()
            ->assertSessionHas('success');

        $activity->refresh();
        $this->assertFalse($activity->evaluation_open);
        $this->assertNotNull($activity->evaluation_status_changed_at);
        $this->assertSame($owner->id, $activity->evaluation_status_changed_by);

        $this->actingAs($owner)
            ->post(route('ams.activities.evaluation-period.toggle', $activity), ['open' => true])
            ->assertRedirect();

        $this->assertTrue($activity->fresh()->evaluation_open);
    }

    public function test_co_proponent_can_toggle_evaluation_period(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner);
        $coProponent = $this->userWithPermission('activities.view_all');
        ActivityCoProponent::create(['activity_id' => $activity->id, 'employee_id' => $coProponent->id]);

        $this->actingAs($coProponent)
            ->post(route('ams.activities.evaluation-period.toggle', $activity), ['open' => false])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse($activity->fresh()->evaluation_open);
    }

    public function test_evaluation_committee_permission_holder_can_toggle_evaluation_period(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner);
        $committeeMember = $this->userWithPermission('activities.evaluation_committee');

        $this->actingAs($committeeMember)
            ->post(route('ams.activities.evaluation-period.toggle', $activity), ['open' => false])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse($activity->fresh()->evaluation_open);
    }

    public function test_unrelated_user_cannot_toggle_evaluation_period(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner);
        $stranger = $this->userWithPermission('activities.view_all');

        $this->actingAs($stranger)
            ->post(route('ams.activities.evaluation-period.toggle', $activity), ['open' => false])
            ->assertForbidden();

        $this->assertTrue($activity->fresh()->evaluation_open);
    }

    public function test_closed_period_shows_closed_message_instead_of_in_house_form(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner, ['evaluation_open' => false]);
        $attendee = User::factory()->create();
        ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => $attendee->id,
            'participant_type' => 'employee', 'attended' => 'yes',
        ]);
        $hash = md5($attendee->id . '-' . $activity->id);

        $this->get(route('ams.activities.evaluate.show', [$activity, $hash]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AMS/Evaluate')
                ->where('evaluationClosed', true)
            );
    }

    public function test_closed_period_blocks_in_house_evaluation_submission(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner, ['evaluation_open' => false]);
        $attendee = User::factory()->create();
        ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => $attendee->id,
            'participant_type' => 'employee', 'attended' => 'yes',
        ]);
        $hash = md5($attendee->id . '-' . $activity->id);

        $this->post(route('ams.activities.evaluate.store', [$activity, $hash]), [
            'obj_1' => 'agree', 'obj_2' => 'agree', 'obj_3' => 'agree', 'obj_4' => 'agree',
            'mgmt_1' => 'agree', 'mgmt_2' => 'agree', 'mgmt_3' => 'agree',
            'mgmt_4' => 'agree', 'mgmt_5' => 'agree', 'mgmt_6' => 'agree',
            'phys_1' => 'agree', 'phys_2' => 'agree', 'phys_3' => 'agree',
        ])->assertRedirect();

        $this->assertDatabaseCount('ams_activity_evaluations', 0);
    }

    public function test_closed_period_blocks_tws_evaluation_submission(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner, [
            'activity_type' => Activity::TYPE_TRAINING_WORKSHOP_SEMINAR,
            'evaluation_open' => false,
        ]);
        $speaker = $activity->speakers()->create(['name' => 'Speaker One', 'sort_order' => 0]);
        $attendee = User::factory()->create();
        ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => $attendee->id,
            'participant_type' => 'employee', 'attended' => 'yes',
        ]);
        $hash = md5($attendee->id . '-' . $activity->id);

        $this->post(route('ams.activities.evaluate.store', [$activity, $hash]), [
            'content_1' => 'agree', 'content_2' => 'agree', 'content_3' => 'agree',
            'content_4' => 'agree', 'content_5' => 'agree',
            'mgmt_length_of_program' => 'satisfied', 'mgmt_schedule' => 'satisfied',
            'mgmt_secretariat_support' => 'satisfied', 'mgmt_venue' => 'satisfied',
            'mgmt_accommodation' => 'satisfied', 'mgmt_food_meals' => 'satisfied',
            'overall_1_objectives_accomplished' => 'agree', 'overall_2_knowledge_increased' => 'agree',
            'speakers' => [[
                'speaker_id' => $speaker->id,
                'topic_depth_of_content' => 'excellent', 'topic_scope_coverage' => 'excellent',
                'topic_relevance_appropriateness' => 'excellent', 'attainment_of_objectives' => 'agree',
                'mastery_1_command_of_subject' => 'agree', 'mastery_2_pace_timing' => 'agree',
                'mastery_3_theory_application_balance' => 'agree', 'mastery_4_current_trends' => 'agree',
                'presentation_1_listened' => 'agree', 'presentation_2_answered_questions' => 'agree',
                'presentation_3_inspired_participation' => 'agree', 'presentation_4_held_interest' => 'agree',
                'acceptability_as_speaker' => 'agree',
            ]],
        ])->assertRedirect();

        $this->assertDatabaseCount('ams_activity_tws_evaluations', 0);
    }

    public function test_closed_period_blocks_walkin_evaluation_submission(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner, ['evaluation_open' => false]);

        $this->get(route('ams.activities.evaluate.walkin.show', [$activity, $activity->qr_token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('evaluationClosed', true));

        $this->post(route('ams.activities.evaluate.walkin.store', [$activity, $activity->qr_token]), [
            'sex' => 'male',
            'obj_1' => 'agree', 'obj_2' => 'agree', 'obj_3' => 'agree', 'obj_4' => 'agree',
            'mgmt_1' => 'agree', 'mgmt_2' => 'agree', 'mgmt_3' => 'agree',
            'mgmt_4' => 'agree', 'mgmt_5' => 'agree', 'mgmt_6' => 'agree',
            'phys_1' => 'agree', 'phys_2' => 'agree', 'phys_3' => 'agree',
        ])->assertRedirect();

        $this->assertDatabaseCount('ams_activity_evaluations', 0);
    }

    public function test_open_period_still_allows_evaluation_submission(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner); // evaluation_open defaults true
        $attendee = User::factory()->create();
        ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => $attendee->id,
            'participant_type' => 'employee', 'attended' => 'yes',
        ]);
        $hash = md5($attendee->id . '-' . $activity->id);

        $this->post(route('ams.activities.evaluate.store', [$activity, $hash]), [
            'obj_1' => 'agree', 'obj_2' => 'agree', 'obj_3' => 'agree', 'obj_4' => 'agree',
            'mgmt_1' => 'agree', 'mgmt_2' => 'agree', 'mgmt_3' => 'agree',
            'mgmt_4' => 'agree', 'mgmt_5' => 'agree', 'mgmt_6' => 'agree',
            'phys_1' => 'agree', 'phys_2' => 'agree', 'phys_3' => 'agree',
        ])->assertRedirect();

        $this->assertDatabaseCount('ams_activity_evaluations', 1);
    }

    public function test_certificate_download_still_works_after_period_closed_for_prior_evaluation(): void
    {
        \Illuminate\Support\Facades\Storage::fake('s3');
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeActivity($owner);
        $attendee = User::factory()->create(['email' => uniqid().'@example.test']);
        $participant = ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => $attendee->id,
            'participant_type' => 'employee', 'attended' => 'yes', 'hours_attended' => 8,
            'certificate_path' => 'ams/certificates/already-issued.pdf',
        ]);
        \Illuminate\Support\Facades\Storage::disk('s3')->put('ams/certificates/already-issued.pdf', 'dummy-pdf-content');
        ActivityEvaluation::create([
            'activity_id' => $activity->id, 'participant_type' => 'employee', 'participant_id' => $attendee->id,
        ]);

        // Close the period after the evaluation/certificate already exist.
        $activity->update(['evaluation_open' => false]);

        $this->actingAs($owner)
            ->get(route('ams.activities.certificates.download.participant', [$activity, $participant]))
            ->assertOk();
    }

    private function makeActivity(User $owner, array $overrides = []): Activity
    {
        return Activity::create(array_merge([
            'user_id' => $owner->id,
            'title' => 'Period Test Activity',
            'activity_type' => Activity::TYPE_IN_HOUSE,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
        ], $overrides));
    }

    private function userWithPermission(string $permissionName): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['module' => 'Activities', 'description' => $permissionName],
        );
        $role = Role::create(['name' => 'AMS Period Test '.uniqid()]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        return $user;
    }
}
