<?php

namespace Tests\Feature\Sos;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Sos\SosAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SosAlertHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function responder(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'sos.respond'], ['module' => 'SOS', 'description' => 'x']);
        $role = Role::firstOrCreate(['name' => 'DRRM Coordinator']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        return $user;
    }

    public function test_history_only_returns_closed_alerts(): void
    {
        $reporter = User::factory()->create(['name' => 'Juan Dela Cruz']);
        SosAlert::create(['triggerable_type' => User::class, 'triggerable_id' => $reporter->id, 'alert_type' => 'medical', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now()]);
        SosAlert::create(['triggerable_type' => User::class, 'triggerable_id' => $reporter->id, 'alert_type' => 'medical', 'status' => 'resolved', 'current_tier_order' => 1, 'triggered_at' => now(), 'resolved_at' => now()]);

        $response = $this->actingAs($this->responder())->getJson('/sos/history');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_history_filters_by_alert_type_and_reporter_name(): void
    {
        $reporter = User::factory()->create(['name' => 'Juan Dela Cruz']);
        $other = User::factory()->create(['name' => 'Maria Santos']);
        SosAlert::create(['triggerable_type' => User::class, 'triggerable_id' => $reporter->id, 'alert_type' => 'medical', 'status' => 'resolved', 'current_tier_order' => 1, 'triggered_at' => now(), 'resolved_at' => now()]);
        SosAlert::create(['triggerable_type' => User::class, 'triggerable_id' => $other->id, 'alert_type' => 'security', 'status' => 'resolved', 'current_tier_order' => 1, 'triggered_at' => now(), 'resolved_at' => now()]);

        $response = $this->actingAs($this->responder())->getJson('/sos/history?reporter=Juan');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('medical', $response->json('data.0.alert_type'));
    }

    public function test_history_filters_by_student_reporter_name(): void
    {
        $studentId = DB::table('students')->insertGetId(['pisaysystemID' => 'HIST-1', 'firstname' => 'Ana', 'lastname' => 'Reyes']);
        SosAlert::create(['triggerable_type' => \App\Models\Student::class, 'triggerable_id' => $studentId, 'alert_type' => 'general', 'status' => 'false_alarm', 'current_tier_order' => 1, 'triggered_at' => now()]);

        $response = $this->actingAs($this->responder())->getJson('/sos/history?reporter=Reyes');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
