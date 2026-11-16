<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StressDataGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StressBenchTest extends TestCase
{
    use RefreshDatabase;

    public function test_bench_writes_results_table(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 10:00', 'Asia/Manila'));
        app(StressDataGenerator::class)->run(4, '2026-10-01', '2026-11-12', 1);
        User::factory()->create(['role' => 'admin']);
        $out = tempnam(sys_get_temp_dir(), 'bench');

        $this->artisan('stress:bench', ['--runs' => 1, '--out' => $out])->assertExitCode(0);

        $lines = array_values(array_filter(explode("\n", file_get_contents($out))));
        $this->assertCount(2 + 11, $lines);
        $this->assertStringStartsWith('| # | Request | Median ms | Peak MB | Target | Pass |', $lines[0]);
        $this->assertStringNotContainsString('| ERR |', implode("\n", $lines));
        unlink($out);
    }
}
