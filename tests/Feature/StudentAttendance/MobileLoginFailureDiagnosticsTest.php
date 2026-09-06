<?php

namespace Tests\Feature\StudentAttendance;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

// AtlasGo's login screen has an unexplained "generic" failure branch that
// only fires when it gets a real HTTP response it can't parse into our
// usual JSON shape (see login_screen.dart) — App Review hit this exact
// message on 2026-09-05 with no corresponding trace anywhere in our own
// logs (nginx, Laravel, ALB metrics) or in Cloudflare's Security Events.
// This endpoint lets the client report what it actually saw, landing in
// CloudWatch, so the NEXT occurrence is diagnosable instead of another
// unattributable guess.
class MobileLoginFailureDiagnosticsTest extends TestCase
{
    public function test_it_logs_a_reported_login_failure(): void
    {
        Log::spy();

        $this->postJson('/api/mobile/diagnostics/login-failure', [
            'status_code'      => 522,
            'response_snippet' => '<html>cloudflare timeout page</html>',
            'platform'         => 'TargetPlatform.iOS',
            'app_version'      => '1.3.0+7',
        ])->assertNoContent();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === 'AtlasGo login failure diagnostic'
                    && $context['status_code'] === 522
                    && $context['response_snippet'] === '<html>cloudflare timeout page</html>'
                    && $context['platform'] === 'TargetPlatform.iOS'
                    && $context['app_version'] === '1.3.0+7'
                    && array_key_exists('ip', $context);
            });
    }

    public function test_status_code_and_response_snippet_are_optional(): void
    {
        Log::spy();

        $this->postJson('/api/mobile/diagnostics/login-failure', [
            'platform'    => 'TargetPlatform.android',
            'app_version' => '1.3.0+7',
        ])->assertNoContent();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context) =>
                $context['status_code'] === null && $context['response_snippet'] === null);
    }

    public function test_platform_and_app_version_are_required(): void
    {
        $this->postJson('/api/mobile/diagnostics/login-failure', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['platform', 'app_version']);
    }

    public function test_response_snippet_is_truncated_server_side(): void
    {
        Log::spy();

        $this->postJson('/api/mobile/diagnostics/login-failure', [
            'response_snippet' => str_repeat('x', 5000),
            'platform'         => 'TargetPlatform.iOS',
            'app_version'      => '1.3.0+7',
        ])->assertUnprocessable();
    }
}
