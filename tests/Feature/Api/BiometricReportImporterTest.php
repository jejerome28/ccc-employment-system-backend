<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\Employee;
use App\Services\BiometricReportImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiometricReportImporterTest extends TestCase
{
    use RefreshDatabase;

    private const ROW = '751,263,Villaflor James Matthew H.,CCC,-,Male,2026-06-22,Mon.,Default Timetable(00:00-23:59:00),07:04:14,18:37:44,480,0,694,0,0,0,0,P,-';

    private function importer(): BiometricReportImporter
    {
        return app(BiometricReportImporter::class);
    }

    private function file(string $csv): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bio');
        file_put_contents($path, $csv);

        return $path;
    }

    public function test_parses_fixture_data_rows_and_skips_headers_legend_footer(): void
    {
        $result = $this->importer()->parse(base_path('tests/Fixtures/biometric-report.csv'));

        $this->assertSame([], $result['errors']);
        $this->assertCount(11, $result['rows']);

        $row = $result['rows'][4];
        $this->assertSame(7, $row['line']);
        $this->assertSame('263', $row['person_id']);
        $this->assertSame('Villaflor James Matthew H.', $row['name']);
        $this->assertSame('2026-06-22', $row['work_date']);
        $this->assertSame('Default Timetable(00:00-23:59:00)', $row['timetable']);
        $this->assertSame('P', $row['status']);
        $this->assertSame('2026-06-21 23:04:14', $row['clock_in_at']->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $row['clock_in_at']->getTimezone()->getName());
        $this->assertSame('2026-06-22 10:37:44', $row['clock_out_at']->format('Y-m-d H:i:s'));
        $this->assertSame(480, $row['work_minutes']);
        $this->assertSame(694, $row['attended_minutes']);
        $this->assertSame(0, $row['absent_minutes']);

        $absent = $result['rows'][6];
        $this->assertNull($absent['clock_in_at']);
        $this->assertNull($absent['clock_out_at']);
        $this->assertSame(480, $absent['absent_minutes']);
        $this->assertSame('A', $absent['status']);
    }

    public function test_bom_and_crlf_are_tolerated(): void
    {
        $result = $this->importer()->parse($this->file("\xEF\xBB\xBF".self::ROW."\r\n"));

        $this->assertSame([], $result['errors']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('P', $result['rows'][0]['status']);
    }

    public function test_overnight_check_out_moves_to_next_day(): void
    {
        $csv = str_replace(['07:04:14', '18:37:44'], ['22:00:00', '06:00:00'], self::ROW);

        $row = $this->importer()->parse($this->file($csv))['rows'][0];

        $this->assertSame('2026-06-22 14:00:00', $row['clock_in_at']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-22 22:00:00', $row['clock_out_at']->format('Y-m-d H:i:s'));
    }

    public function test_reports_every_bad_cell_with_its_line(): void
    {
        $bad = [
            str_replace(',263,', ',,', self::ROW),
            str_replace('2026-06-22', '2026-13-01', self::ROW),
            str_replace('07:04:14', '7am', self::ROW),
            str_replace(',694,', ',1441,', self::ROW),
            str_replace(',P,', ',present,', self::ROW),
        ];

        $errors = $this->importer()->parse($this->file(implode("\n", $bad)))['errors'];

        $this->assertSame([
            ['line' => 1, 'message' => 'Person ID is missing.'],
            ['line' => 2, 'message' => 'Date "2026-13-01" is not YYYY-MM-DD.'],
            ['line' => 3, 'message' => 'Check-In "7am" is not a time.'],
            ['line' => 4, 'message' => 'Attended "1441" is not 0–1440.'],
            ['line' => 5, 'message' => 'Status "present" is not a report code.'],
        ], $errors);
    }

    public function test_duplicate_person_and_date_is_an_error(): void
    {
        $errors = $this->importer()->parse($this->file(self::ROW."\n".self::ROW))['errors'];

        $this->assertSame([['line' => 2, 'message' => 'Duplicate of line 1.']], $errors);
    }

    public function test_file_without_data_rows_returns_nothing(): void
    {
        $result = $this->importer()->parse($this->file("Seq,Person ID\nDate/Time: 2026-06-30 08:16:49\n"));

        $this->assertSame(['rows' => [], 'errors' => []], $result);
    }

    public function test_save_creates_then_updates_and_keeps_notes(): void
    {
        $e = Employee::factory()->create(['biometric_id' => '263']);
        $rows = $this->importer()->parse(base_path('tests/Fixtures/biometric-report.csv'))['rows'];

        $this->assertSame(['created' => 11, 'updated' => 0, 'unknown' => []], $this->importer()->save($rows));

        $a = Attendance::where('employee_id', $e->id)->where('work_date', '2026-06-22')->first();
        $this->assertSame('2026-06-21 23:04:14', $a->clock_in_at->format('Y-m-d H:i:s'));
        $this->assertSame(694, $a->attended_minutes);
        $this->assertSame('P', $a->status);

        $a->update(['notes' => 'Seminar in Manila']);

        $this->assertSame(['created' => 0, 'updated' => 11, 'unknown' => []], $this->importer()->save($rows));
        $this->assertDatabaseCount('attendances', 11);
        $this->assertSame('Seminar in Manila', $a->fresh()->notes);
    }

    public function test_save_skips_and_reports_unknown_person_ids(): void
    {
        Employee::factory()->create(['biometric_id' => '263']);
        $csv = self::ROW."\n".str_replace(['751,263,Villaflor James Matthew H.'], ['752,999,Dela Cruz Juan'], self::ROW);
        $rows = $this->importer()->parse($this->file($csv))['rows'];

        $result = $this->importer()->save($rows);

        $this->assertSame(1, $result['created']);
        $this->assertSame([['line' => 2, 'person_id' => '999', 'name' => 'Dela Cruz Juan']], $result['unknown']);
        $this->assertDatabaseCount('attendances', 1);
    }
}
