<?php

namespace App\Services;

use App\Enums\EmploymentGroup;
use App\Models\Attendance;
use App\Models\Employee;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection;

class AttendanceSummary
{
    private const TOTAL_KEYS = ['present', 'absent', 'late_count', 'late_minutes', 'undertime_minutes', 'ot_minutes', 'ob', 'leave', 'needs_check'];

    public function __construct(private AttendanceCalculator $calculator, private int $chunkSize = 500) {}

    /** @return array{from: string, to: string, computed_through: ?string, ungrouped_count: int, rows: list<array<string, mixed>>, totals: array<string, int|float>, needs_check: list<array{employee_id: int, name: string, work_date: string, punch: string}>} */
    public function build(string $from, string $to, ?Collection $employees = null, ?string $through = null): array
    {
        $tz = config('attendance.timezone');
        $needsCheck = [];
        $totals = array_fill_keys(self::TOTAL_KEYS, 0);

        // One row per employee in sorted order, even if they have no days in range.
        $employees ??= $this->employees($from, $to);
        $through ??= $this->through($from, $to);
        $rows = [];
        foreach ($employees as $e) {
            $rows[$e->id] = [
                'employee_id' => $e->id,
                'person_id' => $e->biometric_id,
                'name' => $e->full_name,
                'department' => $e->department,
                'employment_group' => $e->employment_group?->value,
                'status' => $e->status,
                'archived' => $e->trashed(),
            ] + array_fill_keys(self::TOTAL_KEYS, 0);
        }

        foreach ($this->walk($from, $to, $employees, $through) as ['employee' => $employee, 'date' => $day, 'attendance' => $a, 'day' => $d]) {
            $row = &$rows[$employee->id];
            foreach (['present', 'absent', 'ob', 'leave', 'late_minutes', 'undertime_minutes', 'ot_minutes'] as $key) {
                $row[$key] += $d[$key];
                $totals[$key] += $d[$key];
            }
            if ($d['late_minutes'] > 0) {
                $row['late_count']++;
                $totals['late_count']++;
            }

            if ($d['status'] === 'needs_check') {
                $row['needs_check']++;
                $totals['needs_check']++;
                $needsCheck[] = [
                    'employee_id' => $employee->id,
                    'name' => $employee->full_name,
                    'work_date' => $day,
                    'punch' => ($a->clock_in_at ?? $a->clock_out_at)->setTimezone($tz)->format('H:i'),
                ];
            }
            unset($row);
        }

        return [
            'from' => $from,
            'to' => $to,
            'computed_through' => $through,
            'ungrouped_count' => $employees->whereNull('employment_group')->count(),
            'rows' => array_values($rows),
            'totals' => $totals,
            'needs_check' => $needsCheck,
        ];
    }

    /**
     * Pass the same $employees/$through to build() and walk() so both see one consistent set.
     *
     * @return \Generator<int, array{employee: Employee, date: string, attendance: ?Attendance, day: array<string, mixed>}>
     */
    public function walk(string $from, string $to, ?Collection $employees = null, ?string $through = null): \Generator
    {
        $through ??= $this->through($from, $to);
        if ($through === null || $through < $from) {
            return;
        }

        $employees ??= $this->employees($from, $to);
        $days = array_map(fn ($d) => $d->toDateString(), iterator_to_array(CarbonPeriod::create($from, $through)));

        foreach ($employees->chunk($this->chunkSize) as $chunk) {
            $attendances = Attendance::whereIn('employee_id', $chunk->pluck('id'))
                ->whereBetween('work_date', [$from, $to])
                ->get()
                ->keyBy(fn (Attendance $a) => "{$a->employee_id}|{$a->work_date}");

            foreach ($chunk as $employee) {
                $hired = $employee->hire_date?->toDateString();

                foreach ($days as $day) {
                    if ($hired !== null && $day < $hired) {
                        continue;
                    }

                    $a = $attendances->get("{$employee->id}|{$day}");

                    yield [
                        'employee' => $employee,
                        'date' => $day,
                        'attendance' => $a,
                        'day' => $this->calculator->day($day, $a?->clock_in_at, $a?->clock_out_at, $a?->remark, $a?->remark_part),
                    ];
                }
            }
        }
    }

    public function through(string $from, string $to): ?string
    {
        $latest = Attendance::latestRecordedDate();

        return $latest === null ? null : min($to, $latest);
    }

    /** @return Collection<int, Employee> sorted by group block order (null last), then name */
    public function employees(string $from, string $to): Collection
    {
        $order = array_flip(array_map(fn (EmploymentGroup $g) => $g->value, EmploymentGroup::cases()));

        return Employee::withTrashed()
            ->where(fn ($q) => $q->where(fn ($q) => $q->active()->whereNull('deleted_at'))->orWhereHas('attendances', fn ($a) => $a->whereBetween('work_date', [$from, $to])))
            ->orderBy('last_name')->orderBy('first_name')->get()
            ->sortBy(fn (Employee $e) => $e->employment_group ? $order[$e->employment_group->value] : count($order))
            ->values();
    }
}
