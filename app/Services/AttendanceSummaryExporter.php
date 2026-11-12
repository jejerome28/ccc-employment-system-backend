<?php

namespace App\Services;

use App\Enums\EmploymentGroup;
use App\Models\Attendance;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class AttendanceSummaryExporter
{
    public const HEADERS = ['Person ID', 'Name', 'Department', 'Group', 'Present', 'Absent (LWOP)', 'Late (times)', 'Late (min)', 'Undertime (min)', 'OT (min)', 'OB', 'Leave', 'Needs check'];

    private const NUMBERS = ['present', 'absent', 'late_count', 'late_minutes', 'undertime_minutes', 'ot_minutes', 'ob', 'leave', 'needs_check'];

    private const DAILY_HEADERS = ['Group', 'Name', 'Date', 'Weekday', 'Status', 'Time in', 'Time out', 'Late (min)', 'Undertime (min)', 'OT (min)', 'Remark'];

    /**
     * @param  array{computed_through?: ?string, ungrouped_count?: int, rows: list<array<string, mixed>>, totals: array<string, int|float>, needs_check: list<array<string, mixed>>}  $summary
     * @param  iterable<array{employee: Employee, date: string, attendance: ?Attendance, day: array<string, mixed>}>  $days  streamed into the Daily sheet
     */
    public function write(array $summary, string $path, iterable $days = []): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Summary');
        $writer->addRow(Row::fromValues(['Computed through '.($summary['computed_through'] ?? '—')]));
        $writer->addRow(Row::fromValues(self::HEADERS));

        foreach ($summary['rows'] as $row) {
            $writer->addRow($this->line($row['person_id'] ?? '', $row['name'], $row['department'] ?? '', $row['employment_group'] ?? null, $row));
        }
        $writer->addRow($this->line('', 'Total', '', null, $summary['totals']));

        if (($summary['ungrouped_count'] ?? 0) > 0) {
            $writer->addRow(Row::fromValues(["{$summary['ungrouped_count']} employee(s) have no employment group."]));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Needs check');
        $writer->addRow(Row::fromValues(['Name', 'Date', 'Punch']));
        foreach ($summary['needs_check'] as $item) {
            $writer->addRow(Row::fromValues([$item['name'], $item['work_date'], $item['punch']]));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Daily');
        $writer->addRow(Row::fromValues(self::DAILY_HEADERS));
        $tz = config('attendance.timezone');
        $time = fn ($t) => $t?->setTimezone($tz)->format('H:i') ?? '';
        foreach ($days as ['employee' => $e, 'date' => $date, 'attendance' => $a, 'day' => $d]) {
            if ($d['status'] === 'off') {
                continue;
            }
            $remark = $a?->remark ? ($a->remark === 'ob' ? 'OB' : 'Leave').match ($a->remark_part) {
                'am' => ' (AM)', 'pm' => ' (PM)', default => '',
            } : '';
            $writer->addRow(new Row([
                new StringCell($e->employment_group?->shortLabel() ?? '', null),
                new StringCell($e->full_name, null),
                new StringCell($date, null),
                new StringCell(CarbonImmutable::parse($date)->format('D'), null),
                new StringCell($d['status'], null),
                new StringCell($time($a?->clock_in_at), null),
                new StringCell($time($a?->clock_out_at), null),
                Cell::fromValue($d['late_minutes']),
                Cell::fromValue($d['undertime_minutes']),
                Cell::fromValue($d['ot_minutes']),
                new StringCell($remark, null),
            ]));
        }

        $writer->close();
    }

    /** @param array<string, int|float> $numbers */
    private function line(string $personId, string $name, string $department, ?string $group, array $numbers): Row
    {
        return new Row([
            new StringCell($personId, null),
            new StringCell($name, null),
            new StringCell($department, null),
            new StringCell($group ? (EmploymentGroup::tryFrom($group)?->shortLabel() ?? $group) : '', null),
            ...array_map(fn (string $key) => Cell::fromValue($numbers[$key]), self::NUMBERS),
        ]);
    }
}
