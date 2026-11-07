<?php

namespace Tests\Support;

use DateTimeImmutable;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class CsvToXlsx
{
    private const DATE = 6;

    private const TIMES = [9, 10];

    public static function convert(string $csv, string $path): void
    {
        $dateStyle = (new Style)->withFormat('yyyy-mm-dd');
        $timeStyle = (new Style)->withFormat('hh:mm:ss');
        $writer = new Writer;
        $writer->openToFile($path);

        foreach (preg_split('/\r?\n/', trim($csv)) as $line) {
            $values = str_getcsv($line, escape: '');
            $isData = ctype_digit($values[0] ?? '');

            $writer->addRow(new Row(array_map(function ($value, int $i) use ($isData, $dateStyle, $timeStyle) {
                if ($isData && $i === self::DATE) {
                    return Cell::fromValue(new DateTimeImmutable($value), $dateStyle);
                }
                if ($isData && in_array($i, self::TIMES, true) && $value !== '-') {
                    return Cell::fromValue(new DateTimeImmutable("1899-12-30 {$value}"), $timeStyle);
                }

                return Cell::fromValue($value);
            }, $values, array_keys($values))));
        }

        $writer->close();
    }
}
