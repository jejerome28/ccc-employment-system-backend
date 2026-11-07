<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class AttendanceSummaryExportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = ['Person ID', 'Name', 'Department', 'Present', 'Absent (LWOP)', 'Late (times)', 'Late (min)', 'Undertime (min)', 'OT (min)', 'OB', 'Leave', 'Needs check'];

    /** @return array<string, list<list<mixed>>> */
    private function sheets(TestResponse $response): array
    {
        $reader = new Reader;
        $reader->open($response->baseResponse->getFile()->getPathname());
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }
        $reader->close();

        return $sheets;
    }

    private function punch(Employee $e, string $date, ?string $in, ?string $out): void
    {
        $at = fn (?string $t) => $t ? CarbonImmutable::parse("{$date} {$t}", 'Asia/Manila')->utc() : null;
        Attendance::create(['employee_id' => $e->id, 'work_date' => $date, 'clock_in_at' => $at($in), 'clock_out_at' => $at($out)]);
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/attendance/summary/export?from=2026-11-09&to=2026-11-15')->assertUnauthorized();
    }

    public function test_invalid_range_is_422(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/attendance/summary/export?from=2026-11-15&to=2026-11-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to'], 'data.errors');
    }

    public function test_exports_summary_with_totals_and_needs_check_sheet(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $a = Employee::factory()->create(['biometric_id' => '1', 'first_name' => 'Ana', 'last_name' => 'Abad', 'department' => 'Registrar', 'hire_date' => '2026-01-01']);
        $this->punch($a, '2026-11-09', '07:20', '18:00');
        $this->punch($a, '2026-11-11', '07:00', '17:00');
        $this->punch($a, '2026-11-12', '07:00', null);
        $this->punch($a, '2026-11-13', '08:00', '12:00');

        $response = $this->get('/api/attendance/summary/export?from=2026-11-09&to=2026-11-15')->assertOk();

        $this->assertStringContainsString('attendance_summary_2026-11-09_2026-11-15.xlsx', $response->headers->get('Content-Disposition'));
        $sheets = $this->sheets($response);
        $summary = $sheets['Summary'];

        $this->assertSame(self::HEADERS, $summary[0]);
        $this->assertEquals(['1', 'Ana Abad', 'Registrar', 2, 1, 1, 5, 60, 0, 0, 0, 1], $summary[1]);
        $this->assertSame(['', 'Total', ''], array_slice(end($summary), 0, 3));
        $this->assertSame([['Name', 'Date', 'Punch'], ['Ana Abad', '2026-11-12', '07:00']], $sheets['Needs check']);
    }
}
