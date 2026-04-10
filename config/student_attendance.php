<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Duplicate Scan Window
    |--------------------------------------------------------------------------
    | If the same student scans within this many minutes of their last scan,
    | the scan is considered a duplicate and is rejected. Prevents accidental
    | double-scans at the gate.
    */
    'duplicate_window_minutes' => env('ATTENDANCE_DUPLICATE_WINDOW', 3),

    /*
    |--------------------------------------------------------------------------
    | Gate Locations
    |--------------------------------------------------------------------------
    | Named gate locations for the dropdown on the kiosk UI. The kiosk operator
    | or admin can select which gate the scanner is stationed at.
    */
    'gate_locations' => [
        'main_gate'    => 'Main Gate',
        'side_gate'    => 'Side Gate',
        'dorm_gate'    => 'Dormitory Gate',
        'gym_entrance' => 'Gymnasium Entrance',
    ],

];
