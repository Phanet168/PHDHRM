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
        'require_qr_token' => env('ATTENDANCE_REQUIRE_QR_TOKEN', true),
        'qr_default_expiry_minutes' => env('ATTENDANCE_QR_DEFAULT_EXPIRY_MINUTES', 2),
        'qr_max_expiry_minutes' => env('ATTENDANCE_QR_MAX_EXPIRY_MINUTES', 30),
        'qr_token_secret' => env('ATTENDANCE_QR_TOKEN_SECRET', ''),
    ],
];
