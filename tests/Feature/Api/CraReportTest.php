<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\ReportSignatory;
use App\Models\User;
use App\Services\CraReportExporter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class CraReportTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/attendance/summary/cra?from=2026-11-02&to=2026-11-15';

    /** @return list<list<mixed>> rows of the first sheet */
    private function rows(TestResponse $response): array
    {
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));
        $reader->open($response->baseResponse->getFile()->getPathname());
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            break;
        }
        $reader->close();

        return $rows;
    }

    private function emp(string $first, string $last, ?string $group, array $extra = []): Employee
    {
        return Employee::factory()->create(['first_name' => $first, 'last_name' => $last, 'employment_group' => $group, 'hire_date' => '2026-01-01'] + $extra);
    }

    private function punch(Employee $e, string $date, ?string $in, ?string $out, array $extra = []): void
    {
        $at = fn (?string $t) => $t ? CarbonImmutable::parse("{$date} {$t}", 'Asia/Manila')->utc() : null;
        Attendance::create(['employee_id' => $e->id, 'work_date' => $date, 'clock_in_at' => $at($in), 'clock_out_at' => $at($out)] + $extra);
    }

    /** @param list<list<mixed>> $rows */
    private function find(array $rows, string $needle, int $col = 0): int
    {
        foreach ($rows as $i => $row) {
            if (str_contains((string) ($row[$col] ?? ''), $needle)) {
                return $i;
            }
        }
        $this->fail("'{$needle}' not found");
    }

    private function download(): array
    {
        Sanctum::actingAs(User::factory()->viewer()->create());

        return $this->rows($this->get(self::URL)->assertOk());
    }

    public function test_period_text_and_sheet_name(): void
    {
        $x = new CraReportExporter;

        $this->assertSame(['title' => 'For the month of October 1-15, 2026', 'sheet' => 'October 1-15'], $x->periodText('2026-10-01', '2026-10-15'));
        $this->assertSame(['title' => 'For the period of October 25 - November 10, 2026', 'sheet' => 'Oct 25-Nov 10'], $x->periodText('2026-10-25', '2026-11-10'));
        $this->assertSame(['title' => 'For the period of December 25, 2026 - January 5, 2027', 'sheet' => 'Dec 25-Jan 5'], $x->periodText('2026-12-25', '2027-01-05'));
    }

    public function test_blocks_in_order_with_titles(): void
    {
        $this->emp('Ana', 'Abad', 'permanent');
        $this->emp('Ben', 'Bola', 'cos_non_teaching');
        $this->emp('Cy', 'Cruz', 'permanent_non_teaching');
        $this->emp('Un', 'Set', null);

        $rows = $this->download();
        $titles = array_values(array_filter(array_column($rows, 0), fn ($v) => str_starts_with((string) $v, 'For the')));

        $this->assertSame([
            'For the month of November 2-15, 2026 (Permanent)',
            'For the month of November 2-15, 2026 (COS- Non Teaching Personnel)',
            'For the month of November 2-15, 2026 (Permanent Non-Teaching Personnel)',
            'For the month of November 2-15, 2026 (COS Full-time Teaching Personnel)',
            'For the month of November 2-15, 2026 (No group set)',
        ], $titles);
        $this->assertSame('Office: CITY COLLEGE OF CALAMBA', $rows[0][1]);
        $this->assertSame('Consolidated Report of Attendance', $rows[2][0]);
    }

    public function test_empty_group_still_has_block(): void
    {
        $this->emp('Ana', 'Abad', 'permanent');

        $rows = $this->download();
        $i = $this->find($rows, '(COS Full-time Teaching Personnel)');

        // blank, 3 header rows, then the placeholder row
        $this->assertSame('—', $rows[$i + 5][1]);
        $this->assertSame('', (string) $rows[$i + 5][0]);
    }

    public function test_ungrouped_employee_goes_to_extra_block(): void
    {
        $this->emp('Un', 'Set', null);

        $rows = $this->download();
        $i = $this->find($rows, '(No group set)');

        $this->assertSame('1', (string) $rows[$i + 5][0]);
        $this->assertSame('SET, UN', $rows[$i + 5][1]);
        $this->assertCount($i + 6, $rows);
        $this->assertSame(-1, $this->findAfter($rows, 'Prepared By:', $i));
    }

    private function findAfter(array $rows, string $needle, int $from): int
    {
        foreach (array_slice($rows, $from, null, true) as $i => $row) {
            if (str_contains((string) ($row[1] ?? ''), $needle)) {
                return $i;
            }
        }

        return -1;
    }

    public function test_no_ungrouped_block_when_everyone_has_a_group(): void
    {
        $this->emp('Ana', 'Abad', 'permanent');

        $rows = $this->download();

        $this->assertSame(-1, $this->findAfter($rows, '(No group set)', 0));
        $this->assertCount(4, array_filter(array_column($rows, 1), fn ($v) => $v === 'Prepared By:'));
    }

    public function test_numbers_blank_zero_and_tardy_days(): void
    {
        $e = $this->emp('Ana', 'Abad', 'permanent');
        $this->punch($e, '2026-11-03', '08:00', '18:00'); // 45 min late after grace
        $this->punch($e, '2026-11-04', '07:00', '16:45'); // 75 min undertime

        $rows = $this->download();
        $row = $rows[$this->find($rows, '(Permanent)') + 5];

        $this->assertSame('ABAD, ANA', $row[1]);
        $this->assertSame('', (string) $row[2]); // absent 0
        $this->assertSame(45, $row[4]);
        $this->assertSame(75, $row[5]);
        // tardy days is numeric 0.25 formatted '0.000' (checked on the style below)
        $this->assertEquals(0.25, $row[6]);
    }

    public function test_tardy_cell_has_three_decimal_format(): void
    {
        $this->emp('Ana', 'Abad', 'permanent');
        Sanctum::actingAs(User::factory()->viewer()->create());
        $zip = new \ZipArchive;
        $zip->open($this->get(self::URL)->baseResponse->getFile()->getPathname());

        // The reader drops styles, so check the number format in the package.
        $this->assertStringContainsString('formatCode="0.000"', $zip->getFromName('xl/styles.xml'));
    }

    public function test_remarks_auto_filled(): void
    {
        $ob = $this->emp('Ob', 'Abad', 'permanent');
        $this->punch($ob, '2026-11-03', null, null, ['remark' => 'ob', 'remark_part' => 'whole']);
        $nc = $this->emp('Nc', 'Bola', 'permanent');
        $this->punch($nc, '2026-11-03', '07:00', null);
        $old = $this->emp('Ar', 'Cruz', 'permanent');
        $this->punch($old, '2026-11-03', '07:00', '17:00');
        $old->delete();

        $rows = $this->download();
        $base = $this->find($rows, '(Permanent)') + 5;

        $this->assertSame('OB 1 day', $rows[$base][7]);
        $this->assertSame('1 day needs check', $rows[$base + 1][7]);
        $this->assertSame('Archived', $rows[$base + 2][7]);
    }

    public function test_signatories_from_table(): void
    {
        ReportSignatory::where('position', 1)->first()->update(['name' => 'NEW PERSON, LPT']);

        $rows = $this->download();

        $this->assertGreaterThan(-1, $this->findAfter($rows, 'NEW PERSON, LPT', 0));
        $this->assertGreaterThan(-1, $this->findAfter($rows, 'College President', 0));
    }

    private function zipFor(string $url = self::URL): \ZipArchive
    {
        Sanctum::actingAs(User::factory()->viewer()->create());
        $zip = new \ZipArchive;
        $zip->open($this->get($url)->baseResponse->getFile()->getPathname());

        return $zip;
    }

    public function test_spacing_and_merges_of_first_two_blocks(): void
    {
        $rows = $this->download();
        $offices = array_keys(array_filter($rows, fn ($r) => str_starts_with((string) ($r[1] ?? ''), 'Office:')));
        $prepared = $this->find($rows, 'Prepared By', 1);
        $lastSig = $offices[1] - 2; // last signatory line, then exactly 1 blank row
        $this->assertSame('', (string) ($rows[$offices[1] - 1][1] ?? ''));
        $this->assertNotSame('', (string) $rows[$lastSig][1]);
        // 1 placeholder data row, then 3 blank rows, then Prepared By
        $this->assertSame($prepared - 4, $this->find($rows, '—', 1));

        $xml = $this->zipFor()->getFromName('xl/worksheets/sheet1.xml');
        preg_match_all('/<mergeCell ref="([^"]+)"/', $xml, $m);
        $o2 = $offices[1] + 1; // 1-based row of block 2's Office line
        $expected = fn (int $o) => [
            'A'.($o + 2).':H'.($o + 2), 'A'.($o + 3).':H'.($o + 3),
            'A'.($o + 5).':A'.($o + 7), 'B'.($o + 5).':B'.($o + 7), 'H'.($o + 5).':H'.($o + 7),
            'C'.($o + 5).':D'.($o + 5), 'E'.($o + 5).':G'.($o + 5),
            'C'.($o + 6).':D'.($o + 6), 'E'.($o + 6).':F'.($o + 6), 'G'.($o + 6).':G'.($o + 7),
        ];
        $this->assertSame($expected(1), array_slice($m[1], 0, 10));
        $this->assertSame($expected($o2), array_slice($m[1], 10, 10));
    }

    public function test_signatory_title_ignores_trailing_and_empty_lines(): void
    {
        ReportSignatory::where('position', 1)->first()->update(['title' => "College President\n\n  \n"]);
        $rows = $this->download();

        $i = $this->find($rows, 'College President', 1);
        $this->assertSame('', (string) ($rows[$i + 1][1] ?? ''));
        $this->assertSame('Checked By:', (string) $rows[$i + 2][1]);
    }

    public function test_any_role_can_download_and_range_validated(): void
    {
        Sanctum::actingAs(User::factory()->viewer()->create());

        $response = $this->get(self::URL)->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('CRA November 2-15.xlsx', $response->headers->get('Content-Disposition'));

        $this->getJson('/api/attendance/summary/cra?from=2026-11-15&to=2026-11-01')->assertUnprocessable();
    }
}
