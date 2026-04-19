<?php

return [
    'name'        => 'HumanResource',

    'setup_rules' => [
        'Time'      => 'time',
        'Basic'     => 'basic',
        'Allowance' => 'allowance',
        'Deduction' => 'deduction',
        'Tax'       => 'tax',
    ],

    'bloodGroups' => [
        'A+'  => 'A+',
        'A-'  => 'A-',
        'B+'  => 'B+',
        'B-'  => 'B-',
        'AB+' => 'AB+',
        'AB-' => 'AB-',
        'O+'  => 'O+',
        'O-'  => 'O-',
    ],

    'attendance' => [
        'require_qr_token' => env('ATTENDANCE_REQUIRE_QR_TOKEN', false),
        'qr_default_expiry_minutes' => env('ATTENDANCE_QR_DEFAULT_EXPIRY_MINUTES', 2),
        'qr_max_expiry_minutes' => env('ATTENDANCE_QR_MAX_EXPIRY_MINUTES', 30),
        'qr_token_secret' => env('ATTENDANCE_QR_TOKEN_SECRET', ''),
        'late_grace_minutes' => env('ATTENDANCE_LATE_GRACE_MINUTES', 10),
        'early_leave_grace_minutes' => env('ATTENDANCE_EARLY_LEAVE_GRACE_MINUTES', 10),
        'device_online_window_minutes' => env('ATTENDANCE_DEVICE_ONLINE_WINDOW_MINUTES', 10),
        'device_recent_activity_limit' => env('ATTENDANCE_DEVICE_RECENT_ACTIVITY_LIMIT', 12),
    ],
];
