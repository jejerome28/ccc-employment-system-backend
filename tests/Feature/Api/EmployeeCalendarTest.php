<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function punch(Employee $e, string $date, ?string $in, ?string $out, array $extra = []): Attendance
    {
        $at = fn (?string $t) => $t ? CarbonImmutable::parse("{$date} {$t}", 'Asia/Manila')->utc() : null;

        return Attendance::create([
            'employee_id' => $e->id,
            'work_date' => $date,
            'clock_in_at' => $at($in),
            'clock_out_at' => $at($out),
        ] + $extra);
    }

    public function test_month_statuses(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'Asia/Manila'));
        config(['attendance.holidays' => ['2026-10-08' => 'Test Day']]);
        $e = Employee::factory()->create(['hire_date' => '2026-10-02']);

        $this->punch($e, '2026-10-05', '07:00', '18:00');
        $this->punch($e, '2026-10-07', '07:00', '17:00');
        $this->punch($e, '2026-10-12', '07:30', '19:20');
        $this->punch($e, '2026-10-13', '07:00', '19:00');
        $this->punch($e, '2026-10-14', '07:00', null);
        $this->punch($e, '2026-10-15', null, null, ['remark' => 'ob']);
        $rest = $this->punch($e, '2026-10-16', '08:00', '12:00');
        $this->punch($e, '2026-10-19', '13:00', '18:00', ['remark' => 'leave', 'remark_part' => 'am']);
        $this->punch($e, '2026-10-20', '07:02', null);

        $data = $this->getJson("/api/employees/{$e->id}/calendar?month=2026-10")->assertOk()->json('data');
        $days = collect($data['days'])->keyBy('date');

        $this->assertCount(31, $data['days']);
        $this->assertSame(4, $data['first_weekday']);
        $this->assertSame('2026-09', $data['prev_month']);
        $this->assertSame('2026-11', $data['next_month']);

        $expected = [
            '2026-10-01' => ['off', '', []],
            '2026-10-03' => ['off', '', []],
            '2026-10-05' => ['complete', 'Full day', []],
            '2026-10-06' => ['absent', 'Absent', []],
            '2026-10-07' => ['undertime', 'Undertime 1h 00m', []],
            '2026-10-08' => ['holiday', 'Test Day', []],
            '2026-10-12' => ['late', 'Late 15m', ['overtime']],
            '2026-10-13' => ['overtime', 'OT 1h 00m', []],
            '2026-10-14' => ['needs_check', 'Missing punch', []],
            '2026-10-15' => ['ob', 'OB', []],
            '2026-10-16' => ['rest_day_worked', 'Worked (rest day)', []],
            '2026-10-19' => ['complete', 'Leave (AM) · Full day', []],
            '2026-10-20' => ['in_progress', 'In since 7:02 AM', []],
            '2026-10-21' => ['future', '', []],
        ];
        foreach ($expected as $date => [$status, $label, $flags]) {
            $this->assertSame([$status, $label, $flags], [$days[$date]['status'], $days[$date]['label'], $days[$date]['flags']], $date);
        }

        $this->assertSame('7:00 AM', $days['2026-10-05']['clock_in']);
        $this->assertNull($days['2026-10-06']['attendance_id']);
        $this->assertSame(80, $days['2026-10-12']['ot_minutes']);
        $this->assertSame($rest->id, $days['2026-10-16']['attendance_id']);
        $this->assertSame('leave', $days['2026-10-19']['remark']);

        $t = $data['totals'];
        $this->assertSame([2, 1, 1, 1, 1, 1, 140], [$t['complete'], $t['absent'], $t['late'], $t['overtime'], $t['needs_check'], $t['ob'], $t['ot_minutes']]);
        $this->assertCount(8, $data['legend']);
    }

    public function test_days_after_last_import_are_future(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'Asia/Manila'));
        $e = Employee::factory()->create(['hire_date' => '2026-10-02']);
        $this->punch($e, '2026-10-12', '07:00', '17:00');

        $data = $this->getJson("/api/employees/{$e->id}/calendar?month=2026-10")->assertOk()->json('data');
        $days = collect($data['days'])->keyBy('date');

        foreach (['2026-10-13', '2026-10-14'] as $date) {
            $this->assertSame(['future', ''], [$days[$date]['status'], $days[$date]['label']], $date);
        }
        $this->assertSame('undertime', $days['2026-10-12']['status']);
        $this->assertSame(4, $data['totals']['absent']);
    }

    public function test_today_with_only_clock_out_is_in_progress(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'Asia/Manila'));
        $e = Employee::factory()->create(['hire_date' => '2026-10-02']);
        $this->punch($e, '2026-10-20', null, '11:30');

        $day = collect($this->getJson("/api/employees/{$e->id}/calendar?month=2026-10")->json('data.days'))->firstWhere('date', '2026-10-20');

        $this->assertSame(['in_progress', 'Out at 11:30 AM'], [$day['status'], $day['label']]);
    }

    public function test_viewer_can_view_calendar(): void
    {
        Sanctum::actingAs(User::factory()->viewer()->create());
        $e = Employee::factory()->create();

        $this->getJson("/api/employees/{$e->id}/calendar?month=2026-10")->assertOk();
    }

    public function test_archived_employee_calendar_404(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $e = Employee::factory()->create();
        $e->delete();

        $this->getJson("/api/employees/{$e->id}/calendar?month=2026-10")->assertNotFound();
    }

    public function test_month_is_required_and_formatted(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $e = Employee::factory()->create();

        $this->getJson("/api/employees/{$e->id}/calendar?month=2026-13")->assertUnprocessable()->assertJsonPath('data.errors.month.0', fn ($m) => is_string($m));
        $this->getJson("/api/employees/{$e->id}/calendar")->assertUnprocessable();
    }
}
