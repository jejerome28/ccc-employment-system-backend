<?php

namespace App\Services;

use App\Models\Attendance;
use Carbon\CarbonInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class AttendanceReportExporter
{
    public const HEADERS = ['Person ID', 'Name', 'Department', 'Date', 'Timetable', 'Attendance Status', 'Check-In', 'Check-out', 'Attended', 'Worked'];

    private const STANDARD_DAY_MINUTES = 480;

    public function write(string $from, string $to, string $path): void
    {
        $tz = config('attendance.timezone');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Attendance');
        $writer->addRow(Row::fromValues(self::HEADERS));

        Attendance::query()
            ->join('employees', 'employees.id', '=', 'attendances.employee_id')
            ->whereBetween('attendances.work_date', [$from, $to])
            ->orderBy('attendances.work_date')
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name')
            ->select('attendances.*')
            ->with('employee')
            ->lazy()
            ->each(fn (Attendance $a) => $writer->addRow(Row::fromValues($this->row($a, $tz))));

        $writer->close();
    }

    /** @return list<string|int|null> */
    private function row(Attendance $a, string $tz): array
    {
        $clock = fn (?CarbonInterface $moment) => $moment?->setTimezone($tz)->format('H:i:s') ?? '';
        $attended = $a->worked_minutes;
        $worked = $a->work_minutes ?? ($attended === null ? null : min($attended, self::STANDARD_DAY_MINUTES));

        return [
            $a->employee->biometric_id ?? '',
            $a->employee->full_name,
            $a->employee->department ?? '',
            $a->work_date,
            $a->timetable ?? '',
            $a->status ?? '',
            $clock($a->clock_in_at),
            $clock($a->clock_out_at),
            $attended ?? '',
            $worked ?? '',
        ];
    }
}
