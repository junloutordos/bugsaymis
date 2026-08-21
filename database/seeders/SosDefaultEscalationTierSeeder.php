<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Sos\SosEscalationTier;
use Illuminate\Database\Seeder;

/**
 * Default SOS escalation tiers — fully editable afterward via the
 * Admin SOS Settings page. Ships a working default so the feature isn't
 * inert until an admin configures it.
 *
 * NOTE: production never auto-seeds — run this via ECS exec after deploy:
 *   php /var/www/artisan db:seed --class=Database\\Seeders\\SosDefaultEscalationTierSeeder --force
 */
class SosDefaultEscalationTierSeeder extends Seeder
{
    private const TIER_1_ROLES = [
        'medical'       => 'Nurse',
        'security'      => 'Security Guard',
        'fire_disaster' => 'DRRM Coordinator',
        'general'       => 'Security Guard',
    ];

    public function run(): void
    {
        foreach (self::TIER_1_ROLES as $alertType => $roleName) {
            $role = Role::where('name', $roleName)->first();

            SosEscalationTier::updateOrCreate(
                ['alert_type' => $alertType, 'order' => 1],
                [
                    'role_id'         => $role?->id,
                    'timeout_minutes' => 10,
                    'channels'        => ['in_app', 'sms'],
                    'notify_external' => false,
                ],
            );

            $admin = Role::where('name', 'Administrator')->first();

            SosEscalationTier::updateOrCreate(
                ['alert_type' => $alertType, 'order' => 2],
                [
                    'role_id'         => $admin?->id,
                    'timeout_minutes' => null,
                    'channels'        => ['in_app', 'sms'],
                    'notify_external' => true,
                ],
            );
        }
    }
}
