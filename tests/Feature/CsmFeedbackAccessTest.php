<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsmFeedbackAccessTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function grantCsmView(string $roleName): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'csm.view'],
            ['module' => 'CSM', 'description' => 'View CSM Feedback dashboard, response list, and export reports'],
        );
        $role = Role::firstOrCreate(['name' => $roleName]);
        $role->permissions()->syncWithoutDetaching($permission);
    }

    public function test_user_with_csm_view_permission_can_access_dashboard(): void
    {
        $user = $this->userWithRole('Evaluation Committee');
        $this->grantCsmView('Evaluation Committee');

        $response = $this->actingAs($user)->get(route('csm.dashboard'));

        $response->assertOk();
    }

    public function test_user_with_csm_view_permission_can_access_list(): void
    {
        $user = $this->userWithRole('Evaluation Committee');
        $this->grantCsmView('Evaluation Committee');

        $response = $this->actingAs($user)->get(route('csm.list'));

        $response->assertOk();
    }

    public function test_user_without_csm_view_permission_is_forbidden(): void
    {
        $user = $this->userWithRole('Faculty');

        $response = $this->actingAs($user)->get(route('csm.dashboard'));

        $response->assertForbidden();
    }

    public function test_administrator_bypasses_permission_check(): void
    {
        $user = $this->userWithRole('Administrator');

        $response = $this->actingAs($user)->get(route('csm.dashboard'));

        $response->assertOk();
    }

    // ── Office QR codes (view/print only) ───────────────────────────────────

    private function makeOffice(): \App\Models\Office
    {
        $division = \App\Models\Division::factory()->create();

        return \App\Models\Office::create([
            'name'        => 'Registrar Office',
            'division_id' => $division->id,
        ]);
    }

    public function test_evaluation_committee_can_view_offices_qr_list(): void
    {
        $user = $this->userWithRole('Evaluation Committee');
        $this->grantCsmView('Evaluation Committee');
        $this->makeOffice();

        $response = $this->actingAs($user)->get(route('csm.offices.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('CSM/Offices'));
    }

    public function test_evaluation_committee_can_preview_office_qr_code(): void
    {
        $user = $this->userWithRole('Evaluation Committee');
        $this->grantCsmView('Evaluation Committee');
        $office = $this->makeOffice();

        $response = $this->actingAs($user)->get(route('offices.qr-survey.preview', $office->id));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_evaluation_committee_can_download_office_qr_pdf(): void
    {
        $user = $this->userWithRole('Evaluation Committee');
        $this->grantCsmView('Evaluation Committee');
        $office = $this->makeOffice();

        $response = $this->actingAs($user)->get(route('offices.qr-survey.pdf', $office->id));

        $response->assertOk();
    }

    public function test_evaluation_committee_cannot_regenerate_office_qr_token(): void
    {
        $user = $this->userWithRole('Evaluation Committee');
        $this->grantCsmView('Evaluation Committee');
        $office = $this->makeOffice();

        $response = $this->actingAs($user)->post(route('offices.qr-survey.regenerate', $office->id));

        $response->assertForbidden();
    }

    public function test_evaluation_committee_cannot_toggle_office_qr_survey(): void
    {
        $user = $this->userWithRole('Evaluation Committee');
        $this->grantCsmView('Evaluation Committee');
        $office = $this->makeOffice();

        $response = $this->actingAs($user)->put(route('offices.qr-survey.toggle', $office->id));

        $response->assertForbidden();
    }

    public function test_evaluation_committee_cannot_edit_office(): void
    {
        $user = $this->userWithRole('Evaluation Committee');
        $this->grantCsmView('Evaluation Committee');
        $office = $this->makeOffice();

        $response = $this->actingAs($user)->put(route('offices.update', $office->id), [
            'name'        => 'Renamed Office',
            'division_id' => $office->division_id,
        ]);

        $response->assertForbidden();
    }

    public function test_evaluation_committee_cannot_delete_office(): void
    {
        $user = $this->userWithRole('Evaluation Committee');
        $this->grantCsmView('Evaluation Committee');
        $office = $this->makeOffice();

        $response = $this->actingAs($user)->delete(route('offices.destroy', $office->id));

        $response->assertForbidden();
    }

    public function test_evaluation_committee_cannot_access_offices_management_index(): void
    {
        $user = $this->userWithRole('Evaluation Committee');
        $this->grantCsmView('Evaluation Committee');

        $response = $this->actingAs($user)->get(route('offices.index'));

        $response->assertForbidden();
    }

    public function test_user_without_csm_view_cannot_preview_office_qr_code(): void
    {
        $user = $this->userWithRole('Faculty');
        $office = $this->makeOffice();

        $response = $this->actingAs($user)->get(route('offices.qr-survey.preview', $office->id));

        $response->assertForbidden();
    }
}