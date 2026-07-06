<?php

return [
    // Wi-Fi SSIDs that only exist inside the campus building - connection to
    // any of these is treated as a definitive on-campus signal regardless of
    // which ISP backhauls it.
    'campus_ssids' => array_filter(array_map('trim', explode(',', env('ICT_AGENT_CAMPUS_SSIDS', '')))),

    // Known static public IPs for the campus's ISPs. Not all campus ISPs have
    // a static IP (some are CGNAT'd), so a non-match here is inconclusive,
    // never treated as proof of being off-campus.
    'campus_ips' => array_filter(array_map('trim', explode(',', env('ICT_AGENT_CAMPUS_IPS', '')))),

    // Remote help ("Ask for Remote Help") — attended AnyDesk sessions. The user
    // installs AnyDesk, the tray reports the AnyDesk ID, MIS connects, and the
    // user clicks Accept on the incoming connection (no unattended password).
    // Ships dark: with `enabled` false the tray hides the button and the agent
    // endpoints refuse, so this can deploy ahead of the 1.2.0 agent.
    'remote_help' => [
        'enabled' => env('REMOTE_HELP_ENABLED', false),

        'anydesk' => [
            // Optional: host the official AnyDesk installer on our S3 (served via
            // the existing releases proxy) so the tray offers a controlled,
            // pinned build instead of linking out to anydesk.com. Leave null to
            // fall back to the public download page.
            'installer_s3_key' => env('ANYDESK_INSTALLER_S3_KEY'),
            'installer_sha256' => env('ANYDESK_INSTALLER_SHA256'),
            'download_url'     => env('ANYDESK_DOWNLOAD_URL', 'https://anydesk.com/download'),
        ],

        // A request left unclaimed this long is auto-timed-out.
        'waiting_timeout_minutes' => (int) env('REMOTE_HELP_WAITING_TIMEOUT_MINUTES', 15),

        // Hard ceiling on a single session before it's auto-finalized.
        'session_max_minutes' => (int) env('REMOTE_HELP_SESSION_MAX_MINUTES', 120),

        // How often the tray should poll the current-request endpoint while a
        // request is active (seconds).
        'poll_interval_seconds' => (int) env('REMOTE_HELP_POLL_INTERVAL_SECONDS', 15),
    ],
];
