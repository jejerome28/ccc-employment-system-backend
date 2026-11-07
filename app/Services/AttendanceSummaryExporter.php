<?php

namespace App\Services;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class AttendanceSummaryExporter
{
    public const HEADERS = ['Person ID', 'Name', 'Department', 'Present', 'Absent (LWOP)', 'Late (times)', 'Late (min)', 'Undertime (min)', 'OT (min)', 'OB', 'Leave', 'Needs check'];

    private const NUMBERS = ['present', 'absent', 'late_count', 'late_minutes', 'undertime_minutes', 'ot_minutes', 'ob', 'leave', 'needs_check'];

    /** @param array{rows: list<array<string, mixed>>, totals: array<string, int|float>, needs_check: list<array<string, mixed>>} $summary */
    public function write(array $summary, string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Summary');
        $writer->addRow(Row::fromValues(self::HEADERS));

        foreach ($summary['rows'] as $row) {
            $writer->addRow($this->line($row['person_id'] ?? '', $row['name'], $row['department'] ?? '', $row));
        }
        $writer->addRow($this->line('', 'Total', '', $summary['totals']));

        $writer->addNewSheetAndMakeItCurrent()->setName('Needs check');
        $writer->addRow(Row::fromValues(['Name', 'Date', 'Punch']));
        foreach ($summary['needs_check'] as $item) {
            $writer->addRow(Row::fromValues([$item['name'], $item['work_date'], $item['punch']]));
        }

        $writer->close();
    }

    /** @param array<string, int|float> $numbers */
    private function line(string $personId, string $name, string $department, array $numbers): Row
    {
        return new Row([
            new StringCell($personId, null),
            new StringCell($name, null),
            new StringCell($department, null),
            ...array_map(fn (string $key) => Cell::fromValue($numbers[$key]), self::NUMBERS),
        ]);
    }
}
