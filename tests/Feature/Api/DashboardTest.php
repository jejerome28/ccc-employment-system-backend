<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_stats_count_each_clock_state(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $clockedIn = Employee::factory()->create(['last_name' => 'Abad']);
        $done = Employee::factory()->create(['last_name' => 'Bautista']);
        Employee::factory()->create(['last_name' => 'Cruz']);
        Employee::factory()->inactive()->create();
        Attendance::factory()->for($clockedIn)->create(['work_date' => '2026-09-30', 'clock_in_at' => '2026-09-30 00:00:00']);
        Attendance::factory()->for($done)->create(['work_date' => '2026-09-30', 'clock_in_at' => '2026-09-30 00:00:00', 'clock_out_at' => '2026-09-30 04:30:00']);
        Attendance::factory()->for($done)->create(['work_date' => '2026-09-29', 'clock_in_at' => '2026-09-29 00:00:00', 'clock_out_at' => '2026-09-29 09:00:00']);

        $this->getJson('/api/dashboard?date=2026-09-30')
            ->assertOk()
            ->assertJsonPath('data.stats', [
                'employees' => 4,
                'active' => 3,
                'clocked_in' => 1,
                'completed' => 1,
                'not_in' => 1,
            ])
            ->assertJsonPath('data.hours_today', 4.5)
            ->assertJsonCount(3, 'data.employees')
            ->assertJsonPath('data.employees.0.last_name', 'Abad')
            ->assertJsonPath('data.employees.0.today_attendance.clock_in_at', '2026-09-30T00:00:00Z')
            ->assertJsonPath('data.employees.2.today_attendance', null);
    }

    public function test_without_date_shows_latest_day_with_punches(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $e = Employee::factory()->create();
        Attendance::factory()->for($e)->create(['work_date' => '2026-11-13', 'clock_in_at' => '2026-11-12 23:00:00', 'clock_out_at' => '2026-11-13 10:00:00']);
        Attendance::factory()->for($e)->create(['work_date' => '2026-11-14', 'status' => 'A-S']);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.date', '2026-11-13')
            ->assertJsonPath('data.stats.completed', 1);
    }
}
