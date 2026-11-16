<?php

namespace App\Support;

class AttendanceDate
{
    public static function today(): string
    {
        return now(config('attendance.timezone'))->toDateString();
    }

    public static function thisMonth(): string
    {
        return now(config('attendance.timezone'))->format('Y-m');
    }
}
