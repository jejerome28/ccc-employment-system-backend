<?php

namespace App\Services;

use App\Enums\EmploymentGroup;
use App\Models\ReportSignatory;
use Carbon\CarbonImmutable;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderName;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\BorderWidth;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Options\PageOrientation;
use OpenSpout\Writer\XLSX\Options\PageSetup;
use OpenSpout\Writer\XLSX\Options\PaperSize;
use OpenSpout\Writer\XLSX\Writer;

/** Consolidated Report of Attendance in the City College of Calamba template layout. */
class CraReportExporter
{
    /** @return array{title: string, sheet: string} */
    public function periodText(string $from, string $to): array
    {
        $a = CarbonImmutable::parse($from);
        $b = CarbonImmutable::parse($to);

        if ($a->format('Y-m') === $b->format('Y-m')) {
            $days = $a->day === $b->day ? (string) $a->day : "{$a->day}-{$b->day}";

            return ['title' => "For the month of {$a->format('F')} {$days}, {$a->year}", 'sheet' => "{$a->format('F')} {$days}"];
        }

        $sameYear = $a->year === $b->year;

        return [
            'title' => 'For the period of '.$a->format($sameYear ? 'F j' : 'F j, Y').' - '.$b->format('F j, Y'),
            'sheet' => $a->format('M j').'-'.$b->format('M j'),
        ];
    }

    /**
     * @param  array{from: string, to: string, ungrouped_count?: int, rows: list<array<string, mixed>>}  $summary
     * @param  array<int, string>  $names  employee_id => "LAST, FIRST"; falls back to the row's name uppercased
     */
    public function write(array $summary, string $path, array $names = []): void
    {
        $period = $this->periodText($summary['from'], $summary['to']);
        $options = new Options(pageSetup: new PageSetup(PageOrientation::PORTRAIT, PaperSize::Folio, fitToHeight: 0, fitToWidth: 1));
        $options->setColumnWidth(6, 1);
        $options->setColumnWidth(40, 2);
        $options->setColumnWidthForRange(14, 3, 7);
        $options->setColumnWidth(30, 8);

        $writer = new Writer($options);
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName($period['sheet']);

        $plain = new Style;
        $bold = (new Style)->withFontBold(true);
        $center = (new Style)->withFontBold(true)->withCellAlignment(CellAlignment::CENTER)->withCellVerticalAlignment(CellVerticalAlignment::CENTER)->withShouldWrapText(true);
        $border = new Border(...array_map(fn ($n) => new BorderPart($n, width: BorderWidth::THIN), [
            BorderName::TOP, BorderName::BOTTOM,
            BorderName::LEFT, BorderName::RIGHT,
        ]));
        $head = $center->withBorder($border);
        $cell = $plain->withBorder($border);
        $tardy = $cell->withFormat('0.000');

        $r = 0;
        $line = function (array $values, ?Style $style = null) use ($writer, &$r) {
            $r++;
            $writer->addRow(new Row(array_map(fn ($v) => $v === null || $v === '' ? new StringCell('', $style) : Cell::fromValue($v, $style), $values)));
        };
        $merge = fn (int $c1, int $r1, int $c2, int $r2) => $options->mergeCells($c1, $r1, $c2, $r2);
        $blank = fn () => $line([]);
        $signatories = ReportSignatory::ordered()->values();
        $threshold = (int) config('attendance.tardy_day_minutes');

        $byGroup = collect($summary['rows'])->groupBy(fn ($row) => $row['employment_group'] ?? '');
        $blocks = array_map(fn (EmploymentGroup $g) => [$g, $g->label(), $byGroup->get($g->value, collect())], EmploymentGroup::cases());
        if (($summary['ungrouped_count'] ?? 0) > 0) {
            $blocks[] = [null, '(No group set)', $byGroup->get('', collect())];
        }

        foreach ($blocks as $i => [$group, $suffix, $rows]) {
            if ($i > 0) {
                $blank();
            }
            $line(['', 'Office: '.config('attendance.report_office')], $bold);
            $blank();
            $line(['Consolidated Report of Attendance'], $center);
            $merge(0, $r, 7, $r);
            $line([$period['title'].' '.$suffix], $center);
            $merge(0, $r, 7, $r);
            $blank();

            $top = $r + 1;
            $line(['NO.', 'NAME', 'ABSENCES', '', 'TARDINESS', '', '', 'REMARKS'], $head);
            $line(['', '', 'TOTAL NUMBER OF DAYS', '', 'TOTAL NUMBER OF MINUTES', '', 'TOTAL NUMBER OF DAYS TARDY', ''], $head);
            $line(['', '', 'ABSENT (W/O LEAVE)', 'ON LEAVE', 'LATE', 'UNDERTIME', '', ''], $head);
            foreach ([0, 1, 7] as $c) {
                $merge($c, $top, $c, $top + 2);
            }
            $merge(2, $top, 3, $top);
            $merge(4, $top, 6, $top);
            $merge(2, $top + 1, 3, $top + 1);
            $merge(4, $top + 1, 5, $top + 1);
            $merge(6, $top + 1, 6, $top + 2);

            if ($rows->isEmpty()) {
                $line(['', '—', '', '', '', '', '', ''], $cell);
            }
            foreach ($rows->values() as $n => $row) {
                $days = $threshold > 0 ? round(($row['late_minutes'] + $row['undertime_minutes']) / $threshold, 3) : 0;
                $nz = fn ($v) => $v ? $v : '';
                $r++;
                $writer->addRow(new Row([
                    Cell::fromValue($n + 1, $cell),
                    new StringCell($names[$row['employee_id']] ?? mb_strtoupper($row['name']), $cell),
                    $this->num($nz($row['absent']), $cell),
                    $this->num($nz($row['leave']), $cell),
                    $this->num($nz($row['late_minutes']), $cell),
                    $this->num($nz($row['undertime_minutes']), $cell),
                    $this->num($nz($days), $tardy),
                    new StringCell($this->remarks($row), $cell),
                ]));
            }

            if ($group !== null) {
                $blank();
                $blank();
                $blank();
                foreach ($signatories as $s => $sig) {
                    if ($s > 0) {
                        $blank();
                    }
                    $line(['', $sig->label]);
                    $blank();
                    $line(['', $sig->name], $bold);
                    foreach (array_filter(array_map('trim', explode("\n", $sig->title))) as $t) {
                        $line(['', $t]);
                    }
                }
            }
        }

        $writer->close();
    }

    private function num(int|float|string $v, Style $style): Cell
    {
        return $v === '' ? new StringCell('', $style) : Cell::fromValue($v, $style);
    }

    /** @param array<string, mixed> $row */
    private function remarks(array $row): string
    {
        $days = fn ($n) => ($n + 0).($n == 1 ? ' day' : ' days');
        $parts = array_filter([
            $row['ob'] > 0 ? 'OB '.$days($row['ob']) : null,
            $row['needs_check'] > 0 ? $days($row['needs_check']).' needs check' : null,
            $row['status'] === 'inactive' ? 'Inactive' : null,
            $row['archived'] ? 'Archived' : null,
        ]);

        return implode('; ', $parts);
    }
}
