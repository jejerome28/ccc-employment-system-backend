<?php

return [
    'timezone' => env('ATTENDANCE_TIMEZONE', 'Asia/Manila'),

    'workdays' => array_map('intval', explode(',', env('ATTENDANCE_WORKDAYS', '1,2,3,4'))),

    'start' => env('ATTENDANCE_START', '07:00'),
    'end' => env('ATTENDANCE_END', '18:00'),
    'lunch_start' => env('ATTENDANCE_LUNCH_START', '12:00'),
    'lunch_end' => env('ATTENDANCE_LUNCH_END', '13:00'),
    'grace_minutes' => (int) env('ATTENDANCE_GRACE_MINUTES', 15),

    'holidays' => [
        '2026-11-02' => "All Souls' Day",
        '2026-11-30' => 'Bonifacio Day',
    ],
];
