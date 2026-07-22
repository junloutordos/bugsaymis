<?php

namespace App\Services\StudentAttendance;

use App\Models\StudentAttendance\StudentAttendanceDevice;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class GuardActivityLogger
{
    public function log(
        Request $request,
        string $action,
        ?string $auditableType = null,
        ?int $auditableId = null,
        array $metadata = [],
        ?int $actorId = null,
    ): void {
        /** @var StudentAttendanceDevice|null $device */
        $device = $request->attributes->get('studentAttendanceDevice');
        $operator = $request->attributes->get('studentAttendanceOperator');

        AuditLogger::log([
            'user_id' => $actorId ?? $operator?->id,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'new_values' => array_filter([
                'kiosk_device_id' => $device?->id,
                'device_name' => $device?->name,
                'gate_location' => $device?->gate_location,
                ...$metadata,
            ], static fn ($value) => $value !== null),
        ]);
    }
}
