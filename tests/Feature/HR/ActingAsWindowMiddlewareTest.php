<?php

namespace Tests\Feature\HR;

use App\Models\Division;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\User;
use App\Services\HR\ActingAsService;
use App\Services\HR\SubstitutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActingAsWindowMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function approvedSubstitution(array $overrides = [])
    {
        $chief = User::factory()->create();
        $division = Division::create([
            'division_name' => 'Test Division', 'acronym' => 'TD',
            'division_chief_id' => $chief->id, 'status' => 'active',
        ]);
        $original = User::factory()->create(['division_id' => $division->id]);
        $substitute = User::factory()->create();
        $leaveType = LeaveType::create([
            'code' => 'VL', 'name' => 'Vacation Leave', 'days_per_year' => 15,
            'is_creditable' => true, 'is_deductible' => true, 'requires_approval' => false,
            'with_pay' => true, 'applicable_employment_types' => ['permanent'], 'is_active' => true,
        ]);
        $leave = LeaveApplication::create(array_merge([
            'user_id' => $original->id, 'leave_type_id' => $leaveType->id,
            'date_from' => now()->toDateString(), 'date_to' => now()->addDays(2)->toDateString(),
            'dates' => [], 'days_applied' => 3, 'status' => 'approved',
        ], []));
        $service = app(SubstitutionService::class);
        $substitution = $service->approve($service->nominate($original, $substitute, $leave), $chief);
        if ($overrides) {
            $substitution->update($overrides);
        }

        return [$substitution, $original, $substitute];
    }

    public function test_request_reverts_to_true_user_when_window_expired(): void
    {
        [$substitution, , $substitute] = $this->approvedSubstitution();
        $this->actingAs($substitute);
        $request = \Illuminate\Http\Request::create('/dashboard', 'GET');
        $request->setLaravelSession($this->app['session']->driver());
        app(ActingAsService::class)->start($substitution, $substitute, $request);

        // Window was valid when the act-as session started; now it has lapsed.
        $substitution->update([
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDays(5)->toDateString(),
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertRedirect();
        $this->assertAuthenticatedAs($substitute);
    }

    public function test_request_proceeds_normally_when_no_acting_as_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
    }
}
