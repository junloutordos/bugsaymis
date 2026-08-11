<?php

namespace Tests\Feature\HR;

use App\Models\Division;
use App\Models\HR\ActingAsSession;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveType;
use App\Models\User;
use App\Services\HR\ActingAsService;
use App\Services\HR\SubstitutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ActingAsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function approvedSubstitution()
    {
        $chief = User::factory()->create();
        $division = Division::create([
            'division_name' => 'Test Division', 'acronym' => 'TD',
            'division_chief_id' => $chief->id, 'status' => 'active',
        ]);
        $original = User::factory()->create(['division_id' => $division->id, 'name' => 'Original Person']);
        $substitute = User::factory()->create(['name' => 'Substitute Person']);
        $leaveType = LeaveType::create([
            'code' => 'VL', 'name' => 'Vacation Leave', 'days_per_year' => 15,
            'is_creditable' => true, 'is_deductible' => true, 'requires_approval' => false,
            'with_pay' => true, 'applicable_employment_types' => ['permanent'], 'is_active' => true,
        ]);
        $leave = LeaveApplication::create([
            'user_id' => $original->id, 'leave_type_id' => $leaveType->id,
            'date_from' => now()->toDateString(), 'date_to' => now()->addDays(2)->toDateString(),
            'dates' => [], 'days_applied' => 3, 'status' => 'approved',
        ]);
        $service = app(SubstitutionService::class);
        $substitution = $service->approve($service->nominate($original, $substitute, $leave), $chief);

        return [$substitution, $original, $substitute];
    }

    public function test_start_swaps_auth_user_to_original_and_creates_open_session(): void
    {
        [$substitution, $original, $substitute] = $this->approvedSubstitution();
        $this->actingAs($substitute);
        $request = Request::create('/');
        $request->setLaravelSession($this->app['session']->driver());

        app(ActingAsService::class)->start($substitution, $substitute, $request);

        $this->assertSame($original->id, Auth::id());
        $this->assertDatabaseHas('acting_as_sessions', [
            'substitution_id' => $substitution->id,
            'ended_at' => null,
        ]);
    }

    public function test_exit_reverts_to_true_user_and_closes_session(): void
    {
        [$substitution, $original, $substitute] = $this->approvedSubstitution();
        $this->actingAs($substitute);
        $request = Request::create('/');
        $request->setLaravelSession($this->app['session']->driver());
        app(ActingAsService::class)->start($substitution, $substitute, $request);

        app(ActingAsService::class)->exit($request, 'manual');

        $this->assertSame($substitute->id, Auth::id());
        $session = ActingAsSession::where('substitution_id', $substitution->id)->first();
        $this->assertNotNull($session->ended_at);
        $this->assertSame('manual', $session->ended_reason);
    }
}
