<?php

namespace Tests\Feature\Sos;

use App\Models\Sos\SosAlert;
use App\Models\Student;
use App\Services\Sos\SosAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SosAlertServiceEndByReporterTest extends TestCase
{
    use RefreshDatabase;

    private function student(): Student
    {
        $id = DB::table('students')->insertGetId(['lastname' => 'Santos', 'firstname' => 'Liza', 'status' => 'active']);

        return Student::find($id);
    }

    private function alertFor(Student $student, array $overrides = []): SosAlert
    {
        return SosAlert::create(array_merge([
            'triggerable_type'   => Student::class,
            'triggerable_id'     => $student->id,
            'alert_type'         => 'medical',
            'status'             => 'triggered',
            'current_tier_order' => 1,
            'triggered_at'       => now(),
        ], $overrides));
    }

    public function test_reporter_can_end_an_active_alert(): void
    {
        $student = $this->student();
        $alert = $this->alertFor($student);

        app(SosAlertService::class)->endByReporter($alert, $student);

        $fresh = $alert->fresh();
        $this->assertSame('resolved', $fresh->status);
        $this->assertNotNull($fresh->resolved_at);
        $this->assertNull($fresh->resolved_by);
        $this->assertSame('Ended by reporter.', $fresh->resolution_notes);
        $this->assertDatabaseHas('sos_alert_events', [
            'sos_alert_id' => $alert->id,
            'type'         => 'resolved',
            'actor_type'   => Student::class,
            'actor_id'     => $student->id,
        ]);
    }

    public function test_cannot_end_an_already_resolved_alert(): void
    {
        $student = $this->student();
        $alert = $this->alertFor($student, ['status' => 'resolved']);

        $this->expectException(\RuntimeException::class);

        app(SosAlertService::class)->endByReporter($alert, $student);
    }

    public function test_cannot_end_an_alert_already_marked_false_alarm(): void
    {
        $student = $this->student();
        $alert = $this->alertFor($student, ['status' => 'false_alarm']);

        $this->expectException(\RuntimeException::class);

        app(SosAlertService::class)->endByReporter($alert, $student);
    }
}
