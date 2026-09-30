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

    public function test_time_in_uses_manila_time(): void
    {
        $e = Employee::factory()->create();

        $this->postJson("/api/employees/{$e->id}/time-in")
            ->assertOk()
            ->assertJsonPath('message', 'Timed in.')
            ->assertJsonPath('data.attendance.work_date', '2026-09-30')
            ->assertJsonPath('data.attendance.time_in', '08:05:00');
    }

    public function test_time_in_twice_returns_409(): void
    {
        $e = Employee::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
        $this->postJson("/api/employees/{$e->id}/time-in")->assertOk();

        $this->postJson("/api/employees/{$e->id}/time-in")
            ->assertStatus(409)
            ->assertExactJson(['success' => false, 'message' => 'Maria Santos already timed in at 08:05.', 'data' => null]);

        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_time_out_before_time_in_returns_409(): void
    {
        $e = Employee::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);

        $this->postJson("/api/employees/{$e->id}/time-out")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Maria Santos has not timed in today.');
    }

    public function test_time_out_then_again_returns_409(): void
    {
        $e = Employee::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
        $this->postJson("/api/employees/{$e->id}/time-in")->assertOk();
        $this->travel(9)->hours();

        $this->postJson("/api/employees/{$e->id}/time-out")
            ->assertOk()
            ->assertJsonPath('message', 'Timed out.')
            ->assertJsonPath('data.attendance.time_out', '17:05:00')
            ->assertJsonPath('data.attendance.worked_minutes', 540);

        $this->postJson("/api/employees/{$e->id}/time-out")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Maria Santos already timed out at 17:05.');
    }

    public function test_time_in_unknown_employee_404(): void
    {
        $this->postJson('/api/employees/999/time-in')->assertNotFound();
    }

    public function test_index_lists_date_with_totals_and_active_employees(): void
    {
        $a = Employee::factory()->create(['last_name' => 'Zamora']);
        $b = Employee::factory()->create(['last_name' => 'Abad']);
        Employee::factory()->inactive()->create();
        Attendance::factory()->for($a)->create(['work_date' => '2026-09-29', 'time_in' => '08:00', 'time_out' => '17:00']);
        Attendance::factory()->for($b)->create(['work_date' => '2026-09-29', 'time_in' => '08:00', 'time_out' => '12:00']);
        Attendance::factory()->for($a)->create(['work_date' => '2026-09-30']);

        $this->getJson('/api/attendance?date=2026-09-29')
            ->assertOk()
            ->assertJsonCount(2, 'data.attendances')
            ->assertJsonPath('data.attendances.0.employee.last_name', 'Abad')
            ->assertJsonPath('data.total_minutes', 780)
            ->assertJsonCount(2, 'data.employees');
    }

    public function test_index_defaults_to_today_and_filters_by_q(): void
    {
        $maria = Employee::factory()->create(['first_name' => 'Maria']);
        $jose = Employee::factory()->create(['first_name' => 'Jose']);
        Attendance::factory()->for($maria)->create();
        Attendance::factory()->for($jose)->create();

        $this->getJson('/api/attendance?q=Maria')
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
        $payload = ['employee_id' => $e->id, 'work_date' => '2026-09-29', 'time_in' => '08:00', 'time_out' => '17:00', 'notes' => null];

        $this->postJson('/api/attendance', $payload)->assertOk()->assertJsonPath('message', 'Attendance saved.');
        $this->postJson('/api/attendance', [...$payload, 'time_out' => '18:00'])
            ->assertOk()
            ->assertJsonPath('data.attendance.time_out', '18:00:00');

        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_store_allows_overnight_and_rejects_future_date(): void
    {
        $e = Employee::factory()->create();

        $this->postJson('/api/attendance', ['employee_id' => $e->id, 'work_date' => '2026-09-29', 'time_in' => '22:00', 'time_out' => '06:00'])
            ->assertOk()
            ->assertJsonPath('data.attendance.worked_minutes', 480);

        $this->postJson('/api/attendance', ['employee_id' => $e->id, 'work_date' => '2026-10-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['work_date'], 'data.errors');
    }

    public function test_show_update_destroy(): void
    {
        $a = Attendance::factory()->create(['time_in' => '08:00', 'time_out' => null]);

        $this->getJson("/api/attendance/{$a->id}")
            ->assertOk()
            ->assertJsonPath('data.attendance.employee.id', $a->employee_id);

        $this->putJson("/api/attendance/{$a->id}", ['time_in' => '08:30', 'time_out' => '17:00', 'notes' => 'late'])
            ->assertOk()
            ->assertJsonPath('message', 'Attendance updated.')
            ->assertJsonPath('data.attendance.time_in', '08:30:00')
            ->assertJsonPath('data.attendance.notes', 'late');

        $this->putJson("/api/attendance/{$a->id}", ['time_in' => '8am'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['time_in'], 'data.errors');

        $this->deleteJson("/api/attendance/{$a->id}")
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Attendance deleted.', 'data' => null]);

        $this->getJson("/api/attendance/{$a->id}")->assertNotFound();
    }

    public function test_worked_minutes_prefers_biometric_attended_minutes(): void
    {
        // Row from the biometric report: 07:04:14 to 18:37:44 is 693.5 min, the device reports 694.
        $a = Attendance::create([
            'employee_id' => Employee::factory()->create()->id,
            'work_date' => '2026-06-22',
            'time_in' => '07:04:14',
            'time_out' => '18:37:44',
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
}
