<?php

namespace Tests\Feature\Sos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SosSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_sos_tables_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('sos_alerts', [
            'triggerable_type', 'triggerable_id', 'alert_type', 'is_silent', 'status',
            'lat', 'lng', 'accuracy', 'geofence_zone_id', 'current_tier_order',
            'triggered_at', 'resolved_at', 'resolved_by', 'resolution_notes',
        ]));

        $this->assertTrue(Schema::hasColumns('sos_alert_events', [
            'sos_alert_id', 'type', 'actor_type', 'actor_id', 'payload', 'created_at',
        ]));

        $this->assertTrue(Schema::hasColumns('sos_notification_logs', [
            'sos_alert_id', 'channel', 'recipient_type', 'recipient_id', 'recipient_label', 'sent', 'sent_at',
        ]));

        $this->assertTrue(Schema::hasColumns('sos_escalation_tiers', [
            'alert_type', 'order', 'role_id', 'timeout_minutes', 'channels', 'notify_external',
        ]));

        $this->assertTrue(Schema::hasColumns('sos_escalation_tier_users', [
            'sos_escalation_tier_id', 'user_id',
        ]));

        $this->assertTrue(Schema::hasColumns('sos_external_contacts', [
            'name', 'org', 'phone', 'email', 'alert_types', 'channel', 'active',
        ]));

        $this->assertTrue(Schema::hasColumns('employee_profiles', [
            'mobile_number', 'emergency_contact_name', 'emergency_contact_phone',
        ]));
    }
}
