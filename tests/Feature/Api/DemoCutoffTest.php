<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DemoEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DemoCutoffTest extends TestCase
{
    use RefreshDatabase;

    private const CSV = 'docs/demo/ccc-cutoff-2026-11-01_15.csv';

    private const XLSX = 'docs/demo/ccc-cutoff-2026-11-01_15.xlsx';

    private const EXPECTED = [
        '1001' => [7, 0, 0, 0, 0, 95, 0, 0, 0],
        '1002' => [7, 0, 2, 31, 0, 56, 0, 0, 0],
        '1003' => [7, 0, 0, 0, 90, 100, 0, 0, 0],
        '1004' => [6, 1, 0, 0, 0, 71, 0, 0, 0],
        '1005' => [5, 1, 1, 350, 0, 35, 0, 1, 0],
        '1006' => [6, 0, 0, 0, 0, 79, 0, 0, 1],
        '1007' => [7, 0, 0, 0, 360, 139, 0, 0, 0],
        '1008' => [7, 0, 0, 0, 0, 415, 0, 0, 0],
        '1009' => [5, 2, 1, 15, 358, 54, 0, 0, 0],
        '1010' => [7, 0, 0, 0, 1, 43, 0, 0, 0],
    ];

    private const KEYS = ['present', 'absent', 'late_count', 'late_minutes', 'undertime_minutes', 'ot_minutes', 'ob', 'leave', 'needs_check'];

    private function import(string $path, string $name): void
    {
        $this->postJson('/api/attendance/import', [
            'file' => UploadedFile::fake()->createWithContent($name, file_get_contents(base_path($path))),
        ])->assertOk()->assertJsonPath('data.unknown', []);
    }

    /** @return array<string, list<int|float>> */
    private function summary(): array
    {
        $rows = $this->getJson('/api/attendance/summary?from=2026-11-01&to=2026-11-15')->assertOk()->json('data.rows');

        return collect($rows)->mapWithKeys(fn ($r) => [$r['person_id'] => array_map(fn ($k) => $r[$k], self::KEYS)])->all();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create());
        $this->seed(DemoEmployeeSeeder::class);
    }

    public function test_demo_csv_produces_expected_summary(): void
    {
        $this->import(self::CSV, 'demo.csv');

        $this->assertEquals(self::EXPECTED, $this->summary());
    }

    public function test_demo_xlsx_matches_csv(): void
    {
        $this->import(self::XLSX, 'demo.xlsx');

        $this->assertEquals(self::EXPECTED, $this->summary());
    }

    public function test_nov_5_has_the_requested_mix(): void
    {
        $this->import(self::CSV, 'demo.csv');

        $rows = collect($this->getJson('/api/attendance/summary?from=2026-11-05&to=2026-11-05')->assertOk()->json('data.rows'))
            ->keyBy('person_id');

        $complete = $rows->filter(fn ($r) => $r['present'] == 1 && $r['late_minutes'] === 0 && $r['undertime_minutes'] === 0 && $r['ot_minutes'] === 0);
        $this->assertEqualsCanonicalizing(['1001', '1002', '1003', '1004', '1006', '1010'], $complete->keys()->all());
        $this->assertSame(135, $rows['1008']['ot_minutes']);
        $this->assertSame(100, $rows['1007']['ot_minutes']);
        $this->assertSame(358, $rows['1009']['undertime_minutes']);
        $this->assertEquals(1, $rows['1005']['absent']);
    }

    public function test_marking_ella_ob_on_nov_5_clears_her_absence(): void
    {
        $this->import(self::CSV, 'demo.csv');
        $ella = Employee::where('biometric_id', '1005')->first();
        $day = Attendance::where('employee_id', $ella->id)->where('work_date', '2026-11-05')->first();

        $this->putJson("/api/attendance/{$day->id}", ['remark' => 'ob', 'remark_part' => 'whole'])->assertOk();

        $this->assertEquals([5, 0, 1, 350, 0, 35, 1, 1, 0], $this->summary()['1005']);
    }
}
