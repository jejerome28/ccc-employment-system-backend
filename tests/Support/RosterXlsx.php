<?php

namespace Tests\Support;

use App\Services\EmployeeRosterImporter;
use DateTimeInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class RosterXlsx
{
    /** @param list<?list<mixed>> $rows  null = blank row */
    public static function write(array $rows, string $sheet = 'Roster', ?array $header = null): string
    {
        $path = tempnam(sys_get_temp_dir(), 'roster').'.xlsx';
        $date = (new Style)->withFormat('yyyy-mm-dd');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName($sheet);
        $writer->addRow(Row::fromValues($header ?? EmployeeRosterImporter::HEADER));

        foreach ($rows as $row) {
            $writer->addRow($row === null ? Row::fromValues([]) : new Row(array_map(
                fn ($v) => $v instanceof DateTimeInterface ? Cell::fromValue($v, $date) : Cell::fromValue($v ?? ''),
                array_pad($row, 10, ''),
            )));
        }

        $writer->close();

        return $path;
    }
}
