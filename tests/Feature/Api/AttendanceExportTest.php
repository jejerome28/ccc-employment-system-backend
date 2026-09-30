<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;
use ZipArchive;

class AttendanceExportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = ['Person ID', 'Name', 'Department', 'Date', 'Timetable', 'Attendance Status', 'Check-In', 'Check-out', 'Attended', 'Worked'];

    /** @return list<list<mixed>> */
    private function rows(TestResponse $response): array
    {
        $reader = new Reader;
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

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/attendance/export?from=2026-06-01&to=2026-06-30')->assertUnauthorized();
    }

    public function test_exports_book_layout_in_manila_time(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $james = Employee::factory()->create([
            'biometric_id' => '263', 'first_name' => 'James', 'last_name' => 'Villaflor', 'department' => 'COS Non-Teaching',
        ]);
        $ana = Employee::factory()->create(['first_name' => 'Ana', 'last_name' => 'Abad', 'department' => null]);

        Attendance::factory()->for($james)->create([
            'work_date' => '2026-06-22', 'clock_in_at' => '2026-06-21 23:04:14', 'clock_out_at' => '2026-06-22 10:37:44',
            'timetable' => 'Default Timetable(00:00-23:59:00)', 'status' => 'P', 'work_minutes' => 480, 'attended_minutes' => 694,
        ]);
        Attendance::factory()->for($ana)->create([
            'work_date' => '2026-06-22', 'clock_in_at' => '2026-06-22 00:00:00', 'clock_out_at' => '2026-06-22 10:00:00',
        ]);
        Attendance::factory()->for($james)->create(['work_date' => '2026-07-01']);

        $response = $this->get('/api/attendance/export?from=2026-06-22&to=2026-06-30')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload('attendance_2026-06-22_2026-06-30.xlsx');

        $this->assertSame([
            self::HEADERS,
            ['', 'Ana Abad', '', '2026-06-22', '', '', '08:00:00', '18:00:00', 600, 480],
            ['263', 'James Villaflor', 'COS Non-Teaching', '2026-06-22', 'Default Timetable(00:00-23:59:00)', 'P', '07:04:14', '18:37:44', 694, 480],
        ], $this->rows($response));
    }

    public function test_text_cells_are_never_formulas(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Attendance::factory()->create(['work_date' => '2026-06-22', 'timetable' => '=1+1']);

        $response = $this->get('/api/attendance/export?from=2026-06-22&to=2026-06-22')->assertOk();

        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');

        $this->assertStringNotContainsString('<f>', $sheet);
        $this->assertSame('=1+1', $this->rows($response)[1][4]);
    }

    public function test_empty_range_returns_header_only(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->get('/api/attendance/export?from=2026-06-01&to=2026-06-30')->assertOk();

        $this->assertSame([self::HEADERS], $this->rows($response));
    }

    public function test_range_rules(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/attendance/export')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['from', 'to'], 'data.errors');

        $this->getJson('/api/attendance/export?from=2026-06-30&to=2026-06-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to'], 'data.errors');

        $this->getJson('/api/attendance/export?from=2026-01-01&to=2027-01-02')
            ->assertUnprocessable()
            ->assertJsonPath('data.errors.to.0', 'Range is at most 366 days.');

        $this->getJson('/api/attendance/export?from=2026-01-01&to=2027-01-01')->assertOk();
    }
}
