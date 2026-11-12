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

    private const HEADERS = ['Person ID', 'Name', 'Department', 'Group', 'Present', 'Absent (LWOP)', 'Late (times)', 'Late (min)', 'Undertime (min)', 'OT (min)', 'OB', 'Leave', 'Needs check'];

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
        $a = Employee::factory()->create(['biometric_id' => '1', 'first_name' => 'Ana', 'last_name' => 'Abad', 'department' => 'Registrar', 'employment_group' => 'cos_teaching', 'hire_date' => '2026-01-01']);
        $this->punch($a, '2026-11-09', '07:20', '18:00');
        $this->punch($a, '2026-11-11', '07:00', '17:00');
        $this->punch($a, '2026-11-12', '07:00', null);
        $this->punch($a, '2026-11-13', '08:00', '12:00');

        $response = $this->get('/api/attendance/summary/export?from=2026-11-09&to=2026-11-15')->assertOk();

        $this->assertStringContainsString('attendance_summary_2026-11-09_2026-11-15.xlsx', $response->headers->get('Content-Disposition'));
        $sheets = $this->sheets($response);
        $summary = $sheets['Summary'];

        $this->assertSame(['Computed through 2026-11-13'], $summary[0]);
        $this->assertSame(self::HEADERS, $summary[1]);
        $this->assertEquals(['1', 'Ana Abad', 'Registrar', 'COS Teaching', 2, 1, 1, 5, 60, 0, 0, 0, 1], $summary[2]);
        $this->assertSame(['', 'Total', ''], array_slice(end($summary), 0, 3));
        $this->assertSame([['Name', 'Date', 'Punch'], ['Ana Abad', '2026-11-12', '07:00']], $sheets['Needs check']);
    }

    public function test_export_daily_sheet_lists_non_off_days(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $a = Employee::factory()->create(['first_name' => 'Ana', 'last_name' => 'Abad', 'employment_group' => 'permanent', 'hire_date' => '2026-01-01']);
        $this->punch($a, '2026-11-09', '07:20', '18:00');
        $this->punch($a, '2026-11-10', '07:00', '17:00');

        $daily = $this->sheets($this->get('/api/attendance/summary/export?from=2026-11-09&to=2026-11-15')->assertOk())['Daily'];

        $this->assertSame(['Group', 'Name', 'Date', 'Weekday', 'Status', 'Time in', 'Time out', 'Late (min)', 'Undertime (min)', 'OT (min)', 'Remark'], $daily[0]);
        $this->assertCount(3, $daily);
        $this->assertEquals(['Permanent Teaching', 'Ana Abad', '2026-11-09', 'Mon', 'present', '07:20', '18:00', 5, 0, 0, ''], $daily[1]);
    }

    public function test_export_notes_ungrouped_and_formats_daily_remarks(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $a = Employee::factory()->create(['first_name' => 'Ana', 'last_name' => 'Abad', 'hire_date' => '2026-01-01']);
        Attendance::create(['employee_id' => $a->id, 'work_date' => '2026-11-10', 'remark' => 'ob', 'remark_part' => 'am', 'clock_in_at' => CarbonImmutable::parse('2026-11-10 13:00', 'Asia/Manila')->utc(), 'clock_out_at' => CarbonImmutable::parse('2026-11-10 17:00', 'Asia/Manila')->utc()]);
        Attendance::create(['employee_id' => $a->id, 'work_date' => '2026-11-09', 'remark' => 'leave', 'remark_part' => 'whole']);

        $sheets = $this->sheets($this->get('/api/attendance/summary/export?from=2026-11-09&to=2026-11-15')->assertOk());

        $this->assertSame(['1 employee(s) have no employment group.'], end($sheets['Summary']));
        $this->assertSame('Leave', $sheets['Daily'][1][10]);
        $this->assertSame('OB (AM)', $sheets['Daily'][2][10]);
    }

    public function test_export_without_records_says_computed_through_dash(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $sheets = $this->sheets($this->get('/api/attendance/summary/export?from=2026-11-09&to=2026-11-15')->assertOk());

        $this->assertSame(['Computed through —'], $sheets['Summary'][0]);
    }
}
