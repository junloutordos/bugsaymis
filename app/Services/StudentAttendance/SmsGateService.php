<?php

namespace App\Services\StudentAttendance;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmsGateService
{
    private bool $ready = false;

    public function __construct()
    {
        $url      = config('services.sms_gate.url');
        $username = config('services.sms_gate.username');
        $password = config('services.sms_gate.password');

        if ($url && $username && $password) {
            $this->ready = true;
        } else {
            Log::debug('SmsGate: credentials not configured — SMS notifications disabled.');
        }
    }

    /**
     * Send an SMS via SMS Gate.
     * Phone number is normalised to E.164 Philippine format (+63XXXXXXXXXX).
     * Returns true on a 2xx/202 response, false on any failure.
     */
    public function send(string $phone, string $message): bool
    {
        if (! $this->ready) return false;

        $normalised = $this->normalisePhone($phone);
        if (! $normalised) {
            Log::warning('SmsGate: unparseable phone number, skipping.', ['phone' => $phone]);
            return false;
        }

        try {
            $payload = [
                'phoneNumbers' => [$normalised],
                'message'      => $message,
            ];

            if ($deviceId = config('services.sms_gate.device_id')) {
                $payload['deviceId'] = $deviceId;
            }

            $response = Http::withBasicAuth(
                config('services.sms_gate.username'),
                config('services.sms_gate.password')
            )
                ->timeout(10)
                ->post(config('services.sms_gate.url'), $payload);

            if ($response->successful()) {
                return true;
            }

            Log::warning('SmsGate: non-success response.', [
                'phone'  => $normalised,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return false;
        } catch (Throwable $e) {
            Log::warning('SmsGate: request failed.', [
                'phone' => $normalised,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    private function normalisePhone(string $phone): ?string
    {
        // Strip everything except digits and leading +
        $stripped = preg_replace('/[^\d+]/', '', $phone);

        if (str_starts_with($stripped, '+63')) {
            return $stripped;
        }

        // 09XXXXXXXXX → +639XXXXXXXXX
        if (preg_match('/^09\d{9}$/', $stripped)) {
            return '+63' . substr($stripped, 1);
        }

        // 639XXXXXXXXX (no plus)
        if (preg_match('/^639\d{9}$/', $stripped)) {
            return '+' . $stripped;
        }

        return null;
    }
}
