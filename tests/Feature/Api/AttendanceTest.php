<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create());
        $this->travelTo(Carbon::parse('2026-09-30 08:05:00', 'Asia/Manila'));
    }

    public function test_time_in_stores_utc_now_for_the_given_work_date(): void
    {
        $e = Employee::factory()->create();

        $this->postJson("/api/employees/{$e->id}/time-in", ['work_date' => '2026-09-30'])
            ->assertOk()
            ->assertJsonPath('message', 'Timed in.')
            ->assertJsonPath('data.attendance.work_date', '2026-09-30')
            ->assertJsonPath('data.attendance.clock_in_at', '2026-09-30T00:05:00Z')
            ->assertJsonPath('data.attendance.clock_out_at', null);
    }

    public function test_time_in_requires_work_date(): void
    {
        $e = Employee::factory()->create();

        $this->postJson("/api/employees/{$e->id}/time-in")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['work_date'], 'data.errors');
    }

    public function test_time_in_accepts_manila_today_before_utc_midnight(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 06:00:00', 'Asia/Manila'));
        $e = Employee::factory()->create();

        $this->postJson("/api/employees/{$e->id}/time-in", ['work_date' => '2026-10-01'])
            ->assertOk()
            ->assertJsonPath('data.attendance.clock_in_at', '2026-09-30T22:00:00Z');
    }

    public function test_time_in_twice_returns_409(): void
    {
        $e = Employee::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
        $this->postJson("/api/employees/{$e->id}/time-in", ['work_date' => '2026-09-30'])->assertOk();

        $this->postJson("/api/employees/{$e->id}/time-in", ['work_date' => '2026-09-30'])
            ->assertStatus(409)
            ->assertExactJson(['success' => false, 'message' => 'Maria Santos already timed in at 8:05 AM.', 'data' => null]);

        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_time_out_before_time_in_returns_409(): void
    {
        $e = Employee::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);

        $this->postJson("/api/employees/{$e->id}/time-out", ['work_date' => '2026-09-30'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Maria Santos has not timed in today.');
    }

    public function test_time_out_then_again_returns_409(): void
    {
        $e = Employee::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
        $this->postJson("/api/employees/{$e->id}/time-in", ['work_date' => '2026-09-30'])->assertOk();
        $this->travel(9)->hours();

        $this->postJson("/api/employees/{$e->id}/time-out", ['work_date' => '2026-09-30'])
            ->assertOk()
            ->assertJsonPath('message', 'Timed out.')
            ->assertJsonPath('data.attendance.clock_out_at', '2026-09-30T09:05:00Z')
            ->assertJsonPath('data.attendance.worked_minutes', 540);

        $this->postJson("/api/employees/{$e->id}/time-out", ['work_date' => '2026-09-30'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Maria Santos already timed out at 5:05 PM.');
    }

    public function test_time_in_unknown_employee_404(): void
    {
        $this->postJson('/api/employees/999/time-in', ['work_date' => '2026-09-30'])->assertNotFound();
    }

    public function test_index_lists_date_with_totals_and_active_employees(): void
    {
        $a = Employee::factory()->create(['last_name' => 'Zamora']);
        $b = Employee::factory()->create(['last_name' => 'Abad']);
        Employee::factory()->inactive()->create();
        Attendance::factory()->for($a)->create(['work_date' => '2026-09-29', 'clock_in_at' => '2026-09-29 00:00:00', 'clock_out_at' => '2026-09-29 09:00:00']);
        Attendance::factory()->for($b)->create(['work_date' => '2026-09-29', 'clock_in_at' => '2026-09-29 00:00:00', 'clock_out_at' => '2026-09-29 04:00:00']);
        Attendance::factory()->for($a)->create(['work_date' => '2026-09-30']);

        $this->getJson('/api/attendance?date=2026-09-29')
            ->assertOk()
            ->assertJsonCount(2, 'data.attendances')
            ->assertJsonPath('data.attendances.0.employee.last_name', 'Abad')
            ->assertJsonPath('data.date', '2026-09-29')
            ->assertJsonPath('data.total_minutes', 780)
            ->assertJsonCount(2, 'data.employees');
    }

    public function test_index_without_date_opens_latest_day_with_punches(): void
    {
        $e = Employee::factory()->create();
        Attendance::factory()->for($e)->create(['work_date' => '2026-09-25', 'clock_in_at' => '2026-09-24 23:00:00', 'clock_out_at' => '2026-09-25 10:00:00']);
        Attendance::factory()->for($e)->create(['work_date' => '2026-09-26', 'status' => 'A-S']);

        $this->getJson('/api/attendance')
            ->assertOk()
            ->assertJsonPath('data.date', '2026-09-25')
            ->assertJsonCount(1, 'data.attendances');
    }

    public function test_index_without_date_and_no_punches_uses_manila_today(): void
    {
        $this->getJson('/api/attendance')
            ->assertOk()
            ->assertJsonPath('data.date', '2026-09-30');
    }

    public function test_index_filters_by_q(): void
    {
        $maria = Employee::factory()->create(['first_name' => 'Maria']);
        $jose = Employee::factory()->create(['first_name' => 'Jose']);
        Attendance::factory()->for($maria)->create(['work_date' => '2026-09-30']);
        Attendance::factory()->for($jose)->create(['work_date' => '2026-09-30']);

        $this->getJson('/api/attendance?date=2026-09-30&q=Maria')
            ->assertOk()
            ->assertJsonCount(1, 'data.attendances')
            ->assertJsonPath('data.attendances.0.employee.first_name', 'Maria');
    }

    public function test_index_rejects_bad_date(): void
    {
        $this->getJson('/api/attendance?date=yesterday')->assertUnprocessable();
    }

    public function test_store_upserts_by_employee_and_date(): void
    {
        $e = Employee::factory()->create();
        $payload = [
            'employee_id' => $e->id, 'work_date' => '2026-09-29',
            'clock_in_at' => '2026-09-29T00:00:00Z', 'clock_out_at' => '2026-09-29T09:00:00Z', 'notes' => null,
        ];

        $this->postJson('/api/attendance', $payload)->assertOk()->assertJsonPath('message', 'Attendance saved.');
        $this->postJson('/api/attendance', [...$payload, 'clock_out_at' => '2026-09-29T10:00:00Z'])
            ->assertOk()
            ->assertJsonPath('data.attendance.clock_out_at', '2026-09-29T10:00:00Z')
            ->assertJsonPath('data.attendance.worked_minutes', 600);

        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_store_allows_overnight_and_bounds_work_date(): void
    {
        $e = Employee::factory()->create();

        $this->postJson('/api/attendance', [
            'employee_id' => $e->id, 'work_date' => '2026-09-29',
            'clock_in_at' => '2026-09-29T14:00:00Z', 'clock_out_at' => '2026-09-29T22:00:00Z',
        ])->assertOk()->assertJsonPath('data.attendance.worked_minutes', 480);

        $this->postJson('/api/attendance', ['employee_id' => $e->id, 'work_date' => '2026-10-01'])->assertOk();
        $this->postJson('/api/attendance', ['employee_id' => $e->id, 'work_date' => '2026-10-02'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['work_date'], 'data.errors');
    }

    public function test_store_with_only_clock_in_leaves_worked_minutes_null(): void
    {
        $e = Employee::factory()->create();

        $this->postJson('/api/attendance', [
            'employee_id' => $e->id, 'work_date' => '2026-09-29', 'clock_in_at' => '2026-09-29T00:00:00Z',
        ])
            ->assertOk()
            ->assertJsonPath('data.attendance.clock_out_at', null)
            ->assertJsonPath('data.attendance.worked_minutes', null);
    }

    public function test_store_rejects_clock_out_before_clock_in_and_non_iso(): void
    {
        $e = Employee::factory()->create();

        $this->postJson('/api/attendance', [
            'employee_id' => $e->id, 'work_date' => '2026-09-29',
            'clock_in_at' => '2026-09-29T09:00:00Z', 'clock_out_at' => '2026-09-29T08:00:00Z',
        ])->assertUnprocessable()->assertJsonValidationErrors(['clock_out_at'], 'data.errors');

        $this->postJson('/api/attendance', [
            'employee_id' => $e->id, 'work_date' => '2026-09-29', 'clock_in_at' => '08:00',
        ])->assertUnprocessable()->assertJsonValidationErrors(['clock_in_at'], 'data.errors');
    }

    public function test_show_update_destroy(): void
    {
        $a = Attendance::factory()->create(['clock_in_at' => '2026-09-30 00:00:00']);

        $this->getJson("/api/attendance/{$a->id}")
            ->assertOk()
            ->assertJsonPath('data.attendance.employee.id', $a->employee_id)
            ->assertJsonPath('data.attendance.clock_in_at', '2026-09-30T00:00:00Z');

        $this->putJson("/api/attendance/{$a->id}", [
            'clock_in_at' => '2026-09-30T00:30:00Z', 'clock_out_at' => '2026-09-30T09:00:00Z', 'notes' => 'late',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Attendance updated.')
            ->assertJsonPath('data.attendance.clock_in_at', '2026-09-30T00:30:00Z')
            ->assertJsonPath('data.attendance.notes', 'late');

        $this->putJson("/api/attendance/{$a->id}", ['clock_in_at' => '8am'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['clock_in_at'], 'data.errors');

        $this->deleteJson("/api/attendance/{$a->id}")
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Attendance deleted.', 'data' => null]);

        $this->getJson("/api/attendance/{$a->id}")->assertNotFound();
    }

    public function test_update_validates_a_lone_clock_against_the_stored_counterpart(): void
    {
        $a = Attendance::factory()->create(['clock_in_at' => '2026-09-30 01:00:00', 'clock_out_at' => '2026-09-30 09:00:00']);

        $this->putJson("/api/attendance/{$a->id}", ['clock_out_at' => '2026-09-30T00:00:00Z'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['clock_out_at'], 'data.errors');

        $this->putJson("/api/attendance/{$a->id}", ['clock_in_at' => '2026-09-30T10:00:00Z'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['clock_in_at'], 'data.errors');

        $this->putJson("/api/attendance/{$a->id}", ['clock_out_at' => '2026-09-30T10:00:00Z'])->assertOk();
    }

    public function test_worked_minutes_prefers_biometric_attended_minutes(): void
    {
        $a = Attendance::factory()->create([
            'work_date' => '2026-06-22',
            'clock_in_at' => '2026-06-21 23:04:14',
            'clock_out_at' => '2026-06-22 10:37:44',
            'timetable' => 'Default Timetable(00:00-23:59:00)',
            'status' => 'P',
            'work_minutes' => 480,
            'attended_minutes' => 694,
        ]);

        $this->getJson("/api/attendance/{$a->id}")
            ->assertOk()
            ->assertJsonPath('data.attendance.worked_minutes', 694)
            ->assertJsonPath('data.attendance.work_minutes', 480)
            ->assertJsonPath('data.attendance.status', 'P');
    }
    public function test_store_accepts_remark_without_clocks(): void
    {
        $e = Employee::factory()->create();

        $this->postJson('/api/attendance', [
            'employee_id' => $e->id,
            'work_date' => '2026-09-29',
            'remark' => 'ob',
            'remark_part' => 'whole',
        ])
            ->assertOk()
            ->assertJsonPath('data.attendance.remark', 'ob')
            ->assertJsonPath('data.attendance.remark_part', 'whole')
            ->assertJsonPath('data.attendance.clock_in_at', null);
    }

    public function test_update_sets_half_day_remark(): void
    {
        $a = Attendance::factory()->create(['work_date' => '2026-09-29']);

        $this->putJson("/api/attendance/{$a->id}", ['remark' => 'leave', 'remark_part' => 'am'])
            ->assertOk()
            ->assertJsonPath('data.attendance.remark', 'leave')
            ->assertJsonPath('data.attendance.remark_part', 'am');

        $this->assertDatabaseHas('attendances', ['id' => $a->id, 'remark' => 'leave', 'remark_part' => 'am']);
    }

    public function test_remark_validation(): void
    {
        $a = Attendance::factory()->create(['work_date' => '2026-09-29']);

        $this->putJson("/api/attendance/{$a->id}", ['remark' => 'vacation', 'remark_part' => 'whole'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['remark'], 'data.errors');

        $this->putJson("/api/attendance/{$a->id}", ['remark' => 'ob'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['remark_part'], 'data.errors');

        $this->putJson("/api/attendance/{$a->id}", ['remark' => 'ob', 'remark_part' => 'night'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['remark_part'], 'data.errors');
    }

    public function test_clearing_remark(): void
    {
        $a = Attendance::factory()->create(['work_date' => '2026-09-29']);
        $a->forceFill(['remark' => 'ob', 'remark_part' => 'whole'])->save();

        $this->putJson("/api/attendance/{$a->id}", ['remark' => null, 'remark_part' => null])
            ->assertOk()
            ->assertJsonPath('data.attendance.remark', null)
            ->assertJsonPath('data.attendance.remark_part', null);
    }
}
