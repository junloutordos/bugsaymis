<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $features = [
            'Atlas Sentinel Threat Containment — automatic malware (Windows Defender signal) and network-anomaly (SYN-flood/scan pattern) detection on enrolled devices, with immediate network isolation when a high-confidence threat is found (the device stays reachable to IT — only the rest of the network is blocked)',
            'Security panel per device — live containment status, incident history, one-click Isolate Now / Release, and an exempt-from-auto-containment toggle for devices that should only ever alert',
            'Auto-release safety net — an automatically contained device reconnects on its own after 30 minutes unless IT confirms the incident, bounding the impact of a false positive without anyone needing to act',
        ];

        DB::table('app_versions')->update(['is_current' => false]);
        DB::table('app_versions')->updateOrInsert(
            ['version' => '2.1.0'],
            [
                'date' => '2026-08-10',
                'remarks' => 'Version 2.1.0 — Atlas Sentinel Threat Containment: automatic malware/network-attack detection with network isolation, a new Security panel, and a safety-net auto-release timer.',
                'changes' => json_encode(['features' => $features, 'fixes' => [], 'improvements' => []]),
                'is_current' => true,
                'is_visible' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('app_versions')->where('version', '2.1.0')->delete();
        DB::table('app_versions')->where('version', '2.0.0')->update(['is_current' => true]);
    }
};
