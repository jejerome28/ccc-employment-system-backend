<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-19 10:00', 'Asia/Manila'));
        Sanctum::actingAs(User::factory()->viewer()->create());
    }

    private function emp(string $code, array $extra = []): Employee
    {
        return Employee::factory()->create(['employee_code' => $code, 'hire_date' => '2026-01-05'] + $extra);
    }

    public function test_viewer_finds_one_employee_by_exact_employee_code(): void
    {
        $e = $this->emp('EMP-1003', ['biometric_id' => '1003', 'first_name' => 'Ana', 'last_name' => 'Cruz', 'email' => 'ana@example.com', 'phone' => '0917']);
        $this->emp('EMP-10031');
        Attendance::create(['employee_id' => $e->id, 'work_date' => '2026-10-05',
            'clock_in_at' => CarbonImmutable::parse('2026-10-05 07:00', 'Asia/Manila')->utc(),
            'clock_out_at' => CarbonImmutable::parse('2026-10-05 18:00', 'Asia/Manila')->utc()]);

        $this->postJson('/api/lookup', ['employee_code' => ' EMP-1003 ', 'month' => '2026-10'])
            ->assertOk()
            ->assertJsonPath('data.employee.employee_code', 'EMP-1003')
            ->assertJsonPath('data.employee.person_id', '1003')
            ->assertJsonPath('data.employee.full_name', $e->full_name)
            ->assertJsonMissingPath('data.employee.id')
            ->assertJsonMissingPath('data.employee.email')
            ->assertJsonMissingPath('data.employee.phone')
            ->assertJsonMissingPath('data.calendar.employee')
            ->assertJsonPath('data.calendar.month', '2026-10')
            ->assertJsonCount(1, 'data.attendances')
            ->assertJsonPath('data.attendances.0.work_date', '2026-10-05')
            ->assertJsonMissingPath('data.attendances.0.employee_id')
            ->assertJsonPath('data.days_present', 1);
    }

    public function test_match_ignores_case(): void
    {
        $this->emp('EMP-1003');

        $this->postJson('/api/lookup', ['employee_code' => 'emp-1003'])->assertOk()->assertJsonPath('data.employee.employee_code', 'EMP-1003');
    }

    public function test_month_defaults_to_this_month(): void
    {
        $this->emp('EMP-1003');

        $this->postJson('/api/lookup', ['employee_code' => 'EMP-1003'])->assertOk()->assertJsonPath('data.calendar.month', '2026-11');
    }

    public function test_partial_unknown_and_archived_codes_404(): void
    {
        $this->emp('EMP-1003');
        $this->emp('EMP-2001')->delete();

        foreach (['EMP-100', '1003', 'EMP-9999', 'EMP-2001'] as $code) {
            $this->postJson('/api/lookup', ['employee_code' => $code])
                ->assertNotFound()
                ->assertJsonPath('message', 'No employee with that employee code.');
        }
    }

    public function test_validation(): void
    {
        $this->emp('EMP-1003');

        $this->postJson('/api/lookup', [])->assertUnprocessable()->assertJsonValidationErrors('employee_code', 'data.errors');
        $this->postJson('/api/lookup', ['employee_code' => 'EMP-1003', 'month' => '2026-12'])
            ->assertUnprocessable()->assertJsonValidationErrors('month', 'data.errors');
    }

    public function test_throttled_after_30_per_minute(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/api/lookup', ['employee_code' => 'EMP-9999'])->assertNotFound();
        }
        $this->postJson('/api/lookup', ['employee_code' => 'EMP-9999'])->assertStatus(429);
    }

    public function test_guest_401(): void
    {
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/lookup', ['employee_code' => 'EMP-1003'])->assertUnauthorized();
    }
}
