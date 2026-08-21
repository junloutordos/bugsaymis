<?php

return [
    // Shown to the user when an SOS trigger is blocked for being off-campus
    // or unable to verify location — this is a real phone number the person
    // should call directly, not a dead end.
    'emergency_hotline_number' => env('SOS_EMERGENCY_HOTLINE_NUMBER', '911'),

    'off_campus_message' => 'SOS is for on-campus emergencies. If this is a real emergency, please call the number below directly.',

    // Repeat false-alarm detection window/threshold — crossing this flags
    // the account for admin review, it does not block future triggers.
    'false_alarm_threshold'   => (int) env('SOS_FALSE_ALARM_THRESHOLD', 3),
    'false_alarm_window_days' => (int) env('SOS_FALSE_ALARM_WINDOW_DAYS', 30),

    // Anti-prank friction on the normal (non-silent) trigger flow.
    'hold_confirm_seconds' => 3,
    'countdown_seconds'    => 8,
];
