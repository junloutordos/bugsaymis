<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public static function log(array $data): AuditLog
    {
        $payload = array_merge([
            'user_id' => Auth::id(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::header('User-Agent'),
            'url' => Request::fullUrl(),
        ], $data);

        return AuditLog::create($payload);
    }

    public static function logModelEvent($model, string $action)
    {
        try {
            $old = null;
            $new = null;

            // Normalize action names to the simplified set requested by the user
            $normalized = null;
            if ($action === 'created') {
                $normalized = 'add';
                $new = $model->getAttributes();
            } elseif ($action === 'updated') {
                $changes = $model->getChanges();

                // Detect status transitions to map to approve/decline
                if (array_key_exists('status', $changes)) {
                    $status = strtolower((string) $changes['status']);
                    if (str_contains($status, 'approve')) {
                        $normalized = 'approve';
                    } elseif (str_contains($status, 'decline')) {
                        $normalized = 'decline';
                    }
                }

                // default to edit if no special mapping
                if (! $normalized) $normalized = 'edit';

                $oldVals = [];
                foreach ($changes as $k => $v) {
                    $oldVals[$k] = $model->getOriginal($k);
                }
                $old = $oldVals;
                $new = $changes;
            } elseif ($action === 'deleted') {
                $normalized = 'delete';
                $old = $model->getAttributes();
            }

            return self::log([
                'action' => $normalized ?? $action,
                'auditable_type' => get_class($model),
                'auditable_id' => $model->getKey(),
                'old_values' => $old,
                'new_values' => $new,
            ]);
        } catch (\Throwable $e) {
            // swallow to avoid breaking app flow; developers can inspect logs
            \Log::warning('AuditLogger failed: '.$e->getMessage());
        }

        return null;
    }
}
