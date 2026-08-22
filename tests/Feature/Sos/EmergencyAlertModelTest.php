<?php

namespace Tests\Feature\Sos;

use App\Models\Sos\EmergencyAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmergencyAlertModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeAlert(array $overrides = []): EmergencyAlert
    {
        return EmergencyAlert::create(array_merge([
            'title' => 'Test Alert', 'message' => 'Body', 'severity' => 'warning',
            'audience' => 'all', 'status' => 'active', 'source' => 'manual',
            'created_by' => User::factory()->create()->id,
        ], $overrides));
    }

    public function test_active_scope_excludes_resolved_alerts(): void
    {
        $active = $this->makeAlert();
        $resolved = $this->makeAlert(['status' => 'resolved']);

        $ids = EmergencyAlert::active()->pluck('id');

        $this->assertTrue($ids->contains($active->id));
        $this->assertFalse($ids->contains($resolved->id));
    }

    public function test_visible_to_audience_group_matches_all_and_the_named_group(): void
    {
        $this->makeAlert(['audience' => 'employees']);
        $students = $this->makeAlert(['audience' => 'students']);
        $all = $this->makeAlert(['audience' => 'all']);

        $ids = EmergencyAlert::visibleToAudienceGroup('students')->pluck('id');

        $this->assertTrue($ids->contains($students->id));
        $this->assertTrue($ids->contains($all->id));
        $this->assertCount(2, $ids);
    }

    public function test_is_resolved_reflects_status(): void
    {
        $alert = $this->makeAlert();
        $this->assertFalse($alert->isResolved());

        $alert->update(['status' => 'resolved']);
        $this->assertTrue($alert->fresh()->isResolved());
    }

    public function test_acknowledgment_tracking_works_via_the_shared_trait(): void
    {
        $alert = $this->makeAlert();
        $user = User::factory()->create();

        $this->assertFalse($alert->isAcknowledgedBy($user));
        $alert->acknowledgeFor($user);
        $this->assertTrue($alert->fresh()->isAcknowledgedBy($user));
    }
}
