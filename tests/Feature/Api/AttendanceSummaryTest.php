<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function login(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    private function employee(array $attrs = []): Employee
    {
        return Employee::factory()->create($attrs + ['hire_date' => '2026-01-01']);
    }

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

    private function summary(string $from = '2026-11-09', string $to = '2026-11-15')
    {
        return $this->getJson("/api/attendance/summary?from={$from}&to={$to}");
    }

    private function rowFor(array $data, Employee $e): array
    {
        return collect($data['rows'])->firstWhere('employee_id', $e->id);
    }

    public function test_guest_gets_401(): void
    {
        $this->summary()->assertUnauthorized();
    }

    public function test_summarizes_cutoff_per_employee(): void
    {
        $this->login();
        $a = $this->employee(['biometric_id' => '1', 'last_name' => 'Abad']);
        $b = $this->employee(['last_name' => 'Bautista']);
        $this->punch($a, '2026-11-09', '07:20', '18:00');
        $this->punch($a, '2026-11-11', '07:00', '17:00');
        $this->punch($a, '2026-11-12', '07:00', '19:00');
        $this->punch($a, '2026-11-13', '08:00', '12:00');
        $this->punch($b, '2026-11-12', null, null, ['remark' => 'ob', 'remark_part' => 'whole']);

        $data = $this->summary()->assertOk()->json('data');

        $this->assertSame('2026-11-13', $data['computed_through']);
        $this->assertSame([$a->id, $b->id], array_column($data['rows'], 'employee_id'));

        $rowA = $this->rowFor($data, $a);
        $this->assertSame('1', $rowA['person_id']);
        $this->assertEquals(3, $rowA['present']);
        $this->assertEquals(1, $rowA['absent']);
        $this->assertSame(1, $rowA['late_count']);
        $this->assertSame(5, $rowA['late_minutes']);
        $this->assertSame(60, $rowA['undertime_minutes']);
        $this->assertSame(60, $rowA['ot_minutes']);
        $this->assertSame(0, $rowA['needs_check']);

        $rowB = $this->rowFor($data, $b);
        $this->assertEquals(1, $rowB['ob']);
        $this->assertEquals(3, $rowB['absent']);

        $this->assertEquals(4, $data['totals']['absent']);
        $this->assertEquals(1, $data['totals']['ob']);
        $this->assertSame(5, $data['totals']['late_minutes']);
    }

    public function test_days_after_latest_import_are_skipped(): void
    {
        $this->login();
        $a = $this->employee();
        $this->punch($a, '2026-11-09', '07:00', '18:00');
        $this->punch($a, '2026-11-10', '07:00', '18:00');

        $data = $this->summary()->assertOk()->json('data');

        $this->assertSame('2026-11-10', $data['computed_through']);
        $this->assertEquals(0, $this->rowFor($data, $a)['absent']);
        $this->assertEquals(2, $this->rowFor($data, $a)['present']);
    }

    public function test_days_before_hire_date_are_skipped(): void
    {
        $this->login();
        $other = $this->employee();
        $this->punch($other, '2026-11-12', '07:00', '18:00');
        $new = $this->employee(['hire_date' => '2026-11-11']);

        $data = $this->summary()->assertOk()->json('data');

        $this->assertEquals(2, $this->rowFor($data, $new)['absent']);
    }

    public function test_needs_check_is_listed(): void
    {
        $this->login();
        $a = $this->employee(['first_name' => 'Fe', 'last_name' => 'Torres']);
        $this->punch($a, '2026-11-09', '07:05', null);

        $data = $this->summary('2026-11-09', '2026-11-09')->assertOk()->json('data');

        $this->assertSame(1, $this->rowFor($data, $a)['needs_check']);
        $this->assertSame([[
            'employee_id' => $a->id,
            'name' => 'Fe Torres',
            'work_date' => '2026-11-09',
            'punch' => '07:05',
        ]], $data['needs_check']);
    }

    public function test_inactive_employees_without_records_are_excluded(): void
    {
        $this->login();
        $other = $this->employee();
        $this->punch($other, '2026-11-09', '07:00', '18:00');
        $gone = Employee::factory()->inactive()->create();

        $data = $this->summary()->assertOk()->json('data');

        $this->assertSame([$other->id], array_column($data['rows'], 'employee_id'));
    }

    public function test_range_validation(): void
    {
        $this->login();

        $this->summary('2026-11-15', '2026-11-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to'], 'data.errors');

        $this->summary('2026-10-01', '2026-11-15')
            ->assertUnprocessable()
            ->assertJsonPath('data.errors.to.0', 'The range can be at most 31 days.');
    }
    public function test_future_remark_only_entry_does_not_extend_computed_through(): void
    {
        $this->login();
        $a = $this->employee();
        $this->punch($a, '2026-11-09', '07:00', '18:00');
        $this->punch($a, '2026-11-12', null, null, ['remark' => 'leave', 'remark_part' => 'whole']);

        $data = $this->summary()->assertOk()->json('data');

        $this->assertSame('2026-11-09', $data['computed_through']);
        $this->assertEquals(0, $this->rowFor($data, $a)['absent']);
    }

    public function test_garbage_from_is_422(): void
    {
        $this->login();

        $this->summary('garbage', '2026-11-15')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['from'], 'data.errors');

        $this->getJson('/api/attendance/summary?from[]=x&to=2026-11-15')->assertUnprocessable();
    }

    public function test_employee_set_inactive_mid_cutoff_stays_in_that_cutoff(): void
    {
        $this->login();
        $left = $this->employee(['status' => 'inactive']);
        $this->punch($left, '2026-11-09', '07:00', '18:00');

        $data = $this->summary()->assertOk()->json('data');

        $this->assertEquals(1, $this->rowFor($data, $left)['present']);
    }
}
