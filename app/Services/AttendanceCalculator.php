<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class AttendanceCalculator
{
    /** @return array{status: 'off'|'present'|'absent'|'needs_check'|'remark', present: float, absent: float, ob: float, leave: float, late_minutes: int, undertime_minutes: int, ot_minutes: int} */
    public function day(string $workDate, ?CarbonInterface $in, ?CarbonInterface $out, ?string $remark = null, ?string $remarkPart = null): array
    {
        $tz = config('attendance.timezone');
        $result = [
            'status' => 'off', 'present' => 0.0, 'absent' => 0.0, 'ob' => 0.0, 'leave' => 0.0,
            'late_minutes' => 0, 'undertime_minutes' => 0, 'ot_minutes' => 0,
        ];

        $date = CarbonImmutable::parse($workDate, $tz);
        if (! in_array($date->isoWeekday(), config('attendance.workdays'), true)
            || array_key_exists($workDate, config('attendance.holidays'))) {
            return $result;
        }

        $at = fn (string $time) => CarbonImmutable::parse("{$workDate} {$time}", $tz);
        $start = $at(config('attendance.start'));
        $end = $at(config('attendance.end'));
        $credit = 0.0;

        if ($remark) {
            $part = $remarkPart ?? 'whole';
            $credit = $part === 'whole' ? 1.0 : 0.5;
            $result[$remark] = $credit;

            if ($part === 'whole') {
                return ['status' => 'remark'] + $result;
            }

            if ($part === 'am') {
                $start = $at(config('attendance.lunch_end'));
            } else {
                $end = $at(config('attendance.lunch_start'));
            }
        }

        if (! $in && ! $out) {
            return ['status' => 'absent', 'absent' => 1.0 - $credit] + $result;
        }

        if (! $in || ! $out || $in->equalTo($out)) {
            return ['status' => 'needs_check'] + $result;
        }

        $in = $in->setTimezone($tz);
        $out = $out->setTimezone($tz);

        return [
            'status' => 'present',
            'present' => 1.0 - $credit,
            'late_minutes' => $this->lateMinutes($in, $start),
            'undertime_minutes' => $out->lessThan($end) ? self::minutes($out, $end) : 0,
            'ot_minutes' => $out->greaterThan($end) ? self::minutes($end, $out) : 0,
        ] + $result;
    }

    private function lateMinutes(CarbonInterface $in, CarbonImmutable $start): int
    {
        $graceEnd = $start->addMinutes(config('attendance.grace_minutes'));

        return $in->greaterThan($graceEnd) ? self::minutes($graceEnd, $in) : 0;
    }

    private static function minutes(CarbonInterface $from, CarbonInterface $to): int
    {
        return intdiv($to->getTimestamp() - $from->getTimestamp(), 60);
    }
}
