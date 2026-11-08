<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\CarbonPeriod;

class AttendanceSummary
{
    private const TOTAL_KEYS = ['present', 'absent', 'late_count', 'late_minutes', 'undertime_minutes', 'ot_minutes', 'ob', 'leave', 'needs_check'];

    public function __construct(private AttendanceCalculator $calculator) {}

    /** @return array{from: string, to: string, computed_through: ?string, rows: list<array<string, mixed>>, totals: array<string, int|float>, needs_check: list<array{employee_id: int, name: string, work_date: string, punch: string}>} */
    public function build(string $from, string $to): array
    {
        $latest = Attendance::latestRecordedDate();
        $through = $latest === null ? null : min($to, $latest);
        $tz = config('attendance.timezone');

        $attendances = Attendance::whereBetween('work_date', [$from, $to])
            ->get()
            ->keyBy(fn (Attendance $a) => "{$a->employee_id}|{$a->work_date}");

        $days = $through !== null && $through >= $from
            ? array_map(fn ($d) => $d->toDateString(), iterator_to_array(CarbonPeriod::create($from, $through)))
            : [];

        $rows = [];
        $needsCheck = [];
        $totals = array_fill_keys(self::TOTAL_KEYS, 0);

        $employees = Employee::withTrashed()->where(fn ($q) => $q->where(fn ($q) => $q->active()->whereNull('deleted_at'))->orWhereHas('attendances', fn ($a) => $a->whereBetween('work_date', [$from, $to])))
            ->orderBy('last_name')->orderBy('first_name')->get();

        foreach ($employees as $employee) {
            $row = array_fill_keys(self::TOTAL_KEYS, 0);
            $hired = $employee->hire_date?->toDateString();

            foreach ($days as $day) {
                if ($hired !== null && $day < $hired) {
                    continue;
                }

                $a = $attendances->get("{$employee->id}|{$day}");
                $d = $this->calculator->day($day, $a?->clock_in_at, $a?->clock_out_at, $a?->remark, $a?->remark_part);

                foreach (['present', 'absent', 'ob', 'leave', 'late_minutes', 'undertime_minutes', 'ot_minutes'] as $key) {
                    $row[$key] += $d[$key];
                }
                $row['late_count'] += $d['late_minutes'] > 0 ? 1 : 0;

                if ($d['status'] === 'needs_check') {
                    $row['needs_check']++;
                    $needsCheck[] = [
                        'employee_id' => $employee->id,
                        'name' => $employee->full_name,
                        'work_date' => $day,
                        'punch' => ($a->clock_in_at ?? $a->clock_out_at)->setTimezone($tz)->format('H:i'),
                    ];
                }
            }

            foreach (self::TOTAL_KEYS as $key) {
                $totals[$key] += $row[$key];
            }

            $rows[] = [
                'employee_id' => $employee->id,
                'person_id' => $employee->biometric_id,
                'name' => $employee->full_name,
                'department' => $employee->department,
            ] + $row;
        }

        return [
            'from' => $from,
            'to' => $to,
            'computed_through' => $through,
            'rows' => $rows,
            'totals' => $totals,
            'needs_check' => $needsCheck,
        ];
    }
}
