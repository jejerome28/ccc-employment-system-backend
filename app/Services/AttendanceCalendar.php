<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

class AttendanceCalendar
{
    private const STATUSES = ['complete', 'late', 'undertime', 'overtime', 'absent', 'ob', 'leave', 'needs_check'];

    private const LEGEND = [
        ['status' => 'complete', 'label' => 'Full day'],
        ['status' => 'late', 'label' => 'Late'],
        ['status' => 'undertime', 'label' => 'Undertime'],
        ['status' => 'overtime', 'label' => 'Overtime'],
        ['status' => 'absent', 'label' => 'Absent'],
        ['status' => 'ob', 'label' => 'Official business'],
        ['status' => 'leave', 'label' => 'Leave'],
        ['status' => 'needs_check', 'label' => 'Needs check'],
    ];

    public function __construct(private AttendanceCalculator $calculator) {}

    /** @return array<string, mixed> */
    public function month(Employee $employee, string $month): array
    {
        $tz = config('attendance.timezone');
        $first = CarbonImmutable::createFromFormat('!Y-m', $month, $tz);
        $today = now($tz)->toDateString();
        $hired = $employee->hire_date?->toDateString();
        $latest = Attendance::latestRecordedDate() ?? '';

        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$first->toDateString(), $first->endOfMonth()->toDateString()])
            ->get()
            ->keyBy('work_date');

        $totals = array_fill_keys([...self::STATUSES, 'late_minutes', 'undertime_minutes', 'ot_minutes'], 0);
        $days = [];

        foreach (CarbonPeriod::create($first, $first->endOfMonth()) as $date) {
            $date = $date->toDateString();
            $a = $attendances->get($date);
            $day = $this->day($date, $a, $hired, $today, $latest, $tz);

            if (isset($totals[$day['status']])) {
                $totals[$day['status']]++;
            }
            foreach (['late_minutes', 'undertime_minutes', 'ot_minutes'] as $key) {
                $totals[$key] += $day[$key];
            }
            $days[] = $day;
        }

        return [
            'employee' => ['id' => $employee->id, 'full_name' => $employee->full_name],
            'month' => $month,
            'prev_month' => $first->subMonth()->format('Y-m'),
            'next_month' => $first->addMonth()->format('Y-m'),
            'first_weekday' => $first->isoWeekday(),
            'days' => $days,
            'totals' => $totals,
            'legend' => self::LEGEND,
        ];
    }

    /** @return array<string, mixed> */
    private function day(string $date, ?Attendance $a, ?string $hired, string $today, string $latest, string $tz): array
    {
        $in = $a?->clock_in_at;
        $out = $a?->clock_out_at;
        $d = CarbonImmutable::parse($date, $tz);
        $minutes = ['late_minutes' => 0, 'undertime_minutes' => 0, 'ot_minutes' => 0];
        $flags = [];
        $holidays = config('attendance.holidays');
        $punched = $in || $out;

        if ($hired !== null && $date < $hired) {
            $status = 'off';
            $label = '';
        } elseif (isset($holidays[$date])) {
            [$status, $label] = $punched ? ['rest_day_worked', 'Worked (rest day)'] : ['holiday', $holidays[$date]];
        } elseif (! in_array($d->isoWeekday(), config('attendance.workdays'), true)) {
            [$status, $label] = $punched ? ['rest_day_worked', 'Worked (rest day)'] : ['off', ''];
        } elseif ($date > $today) {
            $status = 'future';
            $label = '';
        } elseif ($date === $today && ! $a?->remark && ! ($in && $out)) {
            [$status, $label] = match (true) {
                $in !== null => ['in_progress', 'In since '.$in->setTimezone($tz)->format('g:i A')],
                $out !== null => ['in_progress', 'Out at '.$out->setTimezone($tz)->format('g:i A')],
                default => ['future', ''],
            };
        } elseif ($date > $latest) {
            $status = 'future';
            $label = '';
        } else {
            $r = $this->calculator->day($date, $in, $out, $a?->remark, $a?->remark_part);
            $minutes = array_intersect_key($r, $minutes);

            if ($r['status'] === 'remark') {
                [$status, $label] = [$a->remark, $a->remark === 'ob' ? 'OB' : 'Leave'];
            } elseif ($r['status'] === 'needs_check') {
                [$status, $label] = ['needs_check', 'Missing punch'];
            } elseif ($r['status'] === 'absent') {
                [$status, $label] = ['absent', 'Absent'];
            } else {
                $issues = array_filter(
                    ['undertime' => $r['undertime_minutes'], 'late' => $r['late_minutes'], 'overtime' => $r['ot_minutes']],
                    fn ($m) => $m > 0,
                );
                $names = array_keys($issues);
                $status = $names[0] ?? 'complete';
                $flags = array_slice($names, 1);
                $label = match ($status) {
                    'undertime' => 'Undertime '.$this->fmt($issues[$status]),
                    'late' => 'Late '.$this->fmt($issues[$status]),
                    'overtime' => 'OT '.$this->fmt($issues[$status]),
                    default => 'Full day',
                };
            }

            if ($a?->remark && $a->remark_part && $a->remark_part !== 'whole') {
                $label = ($a->remark === 'ob' ? 'OB' : 'Leave').' ('.strtoupper($a->remark_part).') · '.$label;
            }
        }

        return [
            'date' => $date,
            'day' => $d->day,
            'weekday' => $d->isoWeekday(),
            'status' => $status,
            'label' => $label,
            'flags' => $flags,
            'clock_in' => $in?->setTimezone($tz)->format('g:i A'),
            'clock_out' => $out?->setTimezone($tz)->format('g:i A'),
            'remark' => $a?->remark,
            'attendance_id' => $a?->id,
        ] + $minutes;
    }

    private function fmt(int $m): string
    {
        return $m < 60 ? "{$m}m" : intdiv($m, 60).'h '.sprintf('%02d', $m % 60).'m';
    }
}
