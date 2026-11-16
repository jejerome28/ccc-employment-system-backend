<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\User;
use App\Support\AttendanceDate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NoFutureDatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-12 10:00', 'Asia/Manila'));
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_manual_attendance_rejects_future_work_date(): void
    {
        $e = Employee::factory()->create();

        $this->postJson('/api/attendance', ['employee_id' => $e->id, 'work_date' => '2026-11-13'])
            ->assertUnprocessable()->assertJsonStructure(['data' => ['errors' => ['work_date']]]);
        $this->postJson('/api/attendance', ['employee_id' => $e->id, 'work_date' => '2026-11-12'])
            ->assertSuccessful();
    }

    public function test_today_uses_manila_not_utc(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-13 06:00', 'Asia/Manila')); // UTC is still 11-12 22:00
        $this->assertSame('2026-11-13', AttendanceDate::today());
        $this->getJson('/api/dashboard?date=2026-11-13')->assertOk();

        $this->travelTo(CarbonImmutable::parse('2026-11-12 23:30', 'UTC')); // Manila is 11-13 07:30
        $this->assertSame('2026-11-13', AttendanceDate::today());

        $this->travelTo(CarbonImmutable::parse('2026-11-12 20:00', 'Asia/Manila')); // UTC 12:00 same day
        $this->getJson('/api/dashboard?date=2026-11-13')->assertUnprocessable();

        $e = Employee::factory()->create();
        $this->travelTo(CarbonImmutable::parse('2026-11-12 23:30', 'Asia/Manila'));
        $this->postJson('/api/attendance', ['employee_id' => $e->id, 'work_date' => '2026-11-12'])->assertSuccessful();

        $this->travelTo(CarbonImmutable::parse('2026-11-12 16:30', 'UTC')); // Nov 13 00:30 Manila
        $this->postJson('/api/attendance', ['employee_id' => $e->id, 'work_date' => '2026-11-13'])->assertSuccessful();
        $this->postJson('/api/attendance', ['employee_id' => $e->id, 'work_date' => '2026-11-14'])
            ->assertUnprocessable()->assertJsonStructure(['data' => ['errors' => ['work_date']]]);
    }

    public function test_clock_rejects_future_work_date(): void
    {
        $e = Employee::factory()->create();

        $this->postJson("/api/employees/{$e->id}/time-in", ['work_date' => '2026-11-13'])->assertUnprocessable();
    }

    public function test_summary_clamps_to_today_and_rejects_future_from(): void
    {
        $this->getJson('/api/attendance/summary?from=2026-11-13&to=2026-11-20')->assertUnprocessable();
        $this->getJson('/api/attendance/summary?from=2026-11-01&to=2026-11-20')
            ->assertOk()->assertJsonPath('data.to', '2026-11-12');

        $this->get('/api/attendance/summary/export?from=2026-11-01&to=2026-11-20')
            ->assertOk()->assertDownload('attendance_summary_2026-11-01_2026-11-12.xlsx');
        $this->getJson('/api/attendance/summary/export?from=2026-11-13&to=2026-11-20')->assertUnprocessable();

        $this->get('/api/attendance/summary/cra?from=2026-11-01&to=2026-11-20')
            ->assertOk()->assertDownload('CRA November 1-12.xlsx');
        $this->getJson('/api/attendance/summary/cra?from=2026-11-13&to=2026-11-20')->assertUnprocessable();

        $this->get('/api/attendance/export?from=2026-11-01&to=2026-11-20')
            ->assertOk()->assertDownload('attendance_2026-11-01_2026-11-12.xlsx');
        $this->getJson('/api/attendance/export?from=2026-11-13&to=2026-11-20')->assertUnprocessable();
    }

    public function test_calendar_and_detail_reject_future_month(): void
    {
        $e = Employee::factory()->create();

        $this->getJson("/api/employees/{$e->id}/calendar?month=2026-12")->assertUnprocessable();
        $this->getJson("/api/employees/{$e->id}/calendar?month=2026-11")->assertOk();
        $this->getJson("/api/employees/{$e->id}?month=2026-12")->assertUnprocessable();
        $this->getJson("/api/employees/{$e->id}?month=2026-11")->assertOk();
    }

    public function test_daily_list_and_dashboard_reject_future_date(): void
    {
        $this->getJson('/api/attendance?date=2026-11-13')->assertUnprocessable();
        $this->getJson('/api/attendance?date=2026-11-12')->assertOk();
        $this->getJson('/api/dashboard?date=2026-11-13')->assertUnprocessable();
        $this->getJson('/api/dashboard?date=2026-11-12')->assertOk();
    }

    public function test_import_skips_future_rows(): void
    {
        Employee::factory()->create(['biometric_id' => '263']);
        $row = fn (int $seq, string $date) => "{$seq},263,Name,D,-,Male,{$date},Thu.,Default,08:00:00,17:00:00,480,0,540,0,0,0,0,P,-";
        $csv = "Seq,Person ID,Name,Department,Position,Gender,Date,Weekday,Timetable,Check-In,Check-out,Work,OT,Attended,Late,Early,Absent,Leave,Status,Records\n"
            .$row(1, '2026-11-12')."\n".$row(2, '2026-11-13')."\n";

        $this->postJson('/api/attendance/import', ['file' => UploadedFile::fake()->createWithContent('r.csv', $csv)])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonCount(1, 'data.future')
            ->assertJsonPath('data.future.0.work_date', '2026-11-13');
    }
}
