<?php

namespace Tests\Feature;

use App\Models\CsmResponse;
use App\Models\Division;
use App\Models\ITJobRequest;
use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeQrSurveyTest extends TestCase
{
    use RefreshDatabase;

    private function makeOffice(array $overrides = []): Office
    {
        $division = Division::factory()->create();

        $office = Office::create(array_merge([
            'name'        => 'Registrar Office',
            'division_id' => $division->id,
        ], $overrides));

        return $office->refresh();
    }

    private function surveyPayload(array $overrides = []): array
    {
        return array_merge([
            'client_type'         => 'citizen',
            'sex'                 => 'male',
            'age'                 => 30,
            'region_of_residence' => 'Caraga',
            'date_of_transaction' => now()->format('Y-m-d'),
            'service_availed'     => ['office_transaction'],
            'service_availed_other' => null,
            'cc1'                 => 1,
            'cc2'                 => 1,
            'cc3'                 => 1,
            'sqd0' => 5, 'sqd1' => 5, 'sqd2' => 5,
            'sqd3' => 5, 'sqd4' => 5, 'sqd5' => 5,
            'sqd6' => 5, 'sqd7' => 5, 'sqd8' => 5,
            'suggestions'         => 'Keep up the good work!',
        ], $overrides);
    }

    // ── Office model ─────────────────────────────────────────────────────────

    public function test_office_auto_generates_a_unique_qr_survey_token_on_create(): void
    {
        $office = $this->makeOffice();

        $this->assertNotEmpty($office->qr_survey_token);
        $this->assertTrue($office->qr_survey_enabled);
    }

    public function test_office_survey_url_points_to_the_public_route(): void
    {
        $office = $this->makeOffice();

        $this->assertStringContainsString('/csm/survey/' . $office->qr_survey_token, $office->surveyUrl());
    }

    // ── Guest can view the survey ────────────────────────────────────────────

    public function test_guest_can_view_the_survey_page_via_a_valid_token(): void
    {
        $office = $this->makeOffice();

        $response = $this->get(route('csm.survey.show', $office->qr_survey_token));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('CSM/PublicOfficeSurvey')
            ->where('office.id', $office->id)
            ->where('office.name', $office->name)
        );
    }

    public function test_invalid_token_returns_404(): void
    {
        $response = $this->get(route('csm.survey.show', 'this-token-does-not-exist'));

        $response->assertNotFound();
    }

    public function test_disabled_survey_token_returns_404_on_show(): void
    {
        $office = $this->makeOffice(['qr_survey_enabled' => false]);

        $response = $this->get(route('csm.survey.show', $office->qr_survey_token));

        $response->assertNotFound();
    }

    // ── Guest can submit anonymously ─────────────────────────────────────────

    public function test_guest_can_submit_the_survey_anonymously(): void
    {
        $office = $this->makeOffice();

        $response = $this->post(route('csm.survey.store', $office->qr_survey_token), $this->surveyPayload());

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseCount('csm_responses', 1);

        $csm = CsmResponse::first();
        $this->assertNull($csm->user_id);
        $this->assertSame(Office::class, $csm->respondable_type);
        $this->assertSame($office->id, $csm->respondable_id);
        $this->assertSame($office->name, $csm->office_availed);
        $this->assertSame('citizen', $csm->client_type);
        $this->assertSame('male', $csm->sex);
    }

    public function test_disabled_survey_token_blocks_submission(): void
    {
        $office = $this->makeOffice(['qr_survey_enabled' => false]);

        $response = $this->post(route('csm.survey.store', $office->qr_survey_token), $this->surveyPayload());

        $response->assertNotFound();
        $this->assertDatabaseCount('csm_responses', 0);
    }

    public function test_invalid_token_blocks_submission(): void
    {
        $response = $this->post(route('csm.survey.store', 'nonexistent-token-xyz'), $this->surveyPayload());

        $response->assertNotFound();
        $this->assertDatabaseCount('csm_responses', 0);
    }

    public function test_submission_requires_all_sqd_fields(): void
    {
        $office = $this->makeOffice();

        $payload = $this->surveyPayload(['sqd0' => null]);

        $response = $this->post(route('csm.survey.store', $office->qr_survey_token), $payload);

        $response->assertSessionHasErrors('sqd0');
        $this->assertDatabaseCount('csm_responses', 0);
    }

    public function test_rate_limiting_blocks_excessive_submissions_from_same_ip(): void
    {
        $office = $this->makeOffice();
        $payload = $this->surveyPayload();

        // throttle:10,1 on csm.survey.store — 11th request within the window should be blocked.
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('csm.survey.store', $office->qr_survey_token), $payload);
        }

        $response = $this->post(route('csm.survey.store', $office->qr_survey_token), $payload);

        $response->assertStatus(429);
    }

    // ── No regression on module-triggered CSM flow ───────────────────────────

    public function test_module_triggered_csm_flow_still_requires_auth_and_ownership(): void
    {
        $requester = User::factory()->create();
        $stranger  = User::factory()->create();
        $divisionChief = User::factory()->create();

        $itjr = ITJobRequest::create([
            'itjr_no'           => 'ITJR-TEST-0001',
            'user_id'           => $requester->id,
            'category'          => 'Hardware',
            'title'             => 'Test Request',
            'description'       => 'Test description',
            'status'            => 'For Approval of DC',
            'divisionchief_id'  => $divisionChief->id,
            'assignedto'        => null,
        ]);

        $payload = $this->surveyPayload([
            'respondable_type' => 'it-job-request',
            'respondable_id'   => $itjr->id,
            'office_availed'   => 'MIS Office',
        ]);
        unset($payload['sex']); // module flow allows null sex too

        // Stranger cannot submit CSM for someone else's request.
        $this->actingAs($stranger)
            ->post(route('csm.store'), $payload)
            ->assertForbidden();

        $this->assertDatabaseCount('csm_responses', 0);

        // The actual requester can submit successfully.
        $this->actingAs($requester)
            ->post(route('csm.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('csm_responses', 1);
        $csm = CsmResponse::first();
        $this->assertSame($requester->id, $csm->user_id);
        $this->assertSame(ITJobRequest::class, $csm->respondable_type);
    }

    public function test_module_triggered_csm_store_requires_authentication(): void
    {
        $office = $this->makeOffice();

        $response = $this->post(route('csm.store'), $this->surveyPayload([
            'respondable_type' => 'it-job-request',
            'respondable_id'   => 1,
            'office_availed'   => $office->name,
        ]));

        $response->assertRedirect(route('login'));
    }
}
