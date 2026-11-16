<?php

namespace Tests\Feature;

use App\Console\Commands\StressSeed;
use App\Models\Employee;
use App\Services\StressDataGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StressSeedTest extends TestCase
{
    use RefreshDatabase;

    private function gen(int $n, string $from, string $to, int $seed = 42): array
    {
        return app(StressDataGenerator::class)->run($n, $from, $to, $seed);
    }

    public function test_generates_one_row_per_employee_day_with_groups(): void
    {
        $r = $this->gen(8, '2026-10-01', '2026-10-31');

        $this->assertSame(8, $r['employees']);
        $this->assertLessThanOrEqual(8 * 31, $r['attendances']);
        $this->assertSame($r['attendances'], DB::table('attendances')->count());
        $this->assertSame([2, 2, 2, 2], array_values(DB::table('employees')->select('employment_group', DB::raw('count(*) c'))->groupBy('employment_group')->pluck('c')->all()));
        $this->assertSame(0, DB::table('attendances')->select('employee_id', 'work_date')->groupBy('employee_id', 'work_date')->havingRaw('count(*) > 1')->get()->count());
        $this->assertLessThanOrEqual('2026-10-31', DB::table('attendances')->max('work_date'));
    }

    public function test_outcome_mix_is_roughly_as_designed(): void
    {
        $this->gen(40, '2026-01-01', '2026-06-30');
        $workdays = DB::table('attendances')->get()->filter(fn ($a) => in_array(CarbonImmutable::parse($a->work_date)->isoWeekday(), [1, 2, 3, 4]));

        $punched = $workdays->filter(fn ($a) => $a->clock_in_at && $a->clock_out_at);
        $late = $punched->filter(fn ($a) => CarbonImmutable::parse($a->clock_in_at, 'UTC')->setTimezone('Asia/Manila')->format('H:i') > '07:15')->count() / $punched->count();
        $absent = $workdays->filter(fn ($a) => ! $a->clock_in_at && ! $a->clock_out_at && ! $a->remark)->count() / $workdays->count();

        $this->assertGreaterThan(0.03, $late);
        $this->assertLessThan(0.10, $late);
        $this->assertGreaterThan(0.01, $absent);
        $this->assertLessThan(0.06, $absent);
    }

    public function test_archived_rows_stop_at_archive_date(): void
    {
        $this->gen(40, '2026-10-01', '2026-10-31');

        $archived = Employee::onlyTrashed()->get();
        $this->assertCount(1, $archived);
        foreach ($archived as $e) {
            $this->assertGreaterThanOrEqual('2026-10-05', $e->deleted_at->toDateString());
            $this->assertLessThanOrEqual('2026-10-25', $e->deleted_at->toDateString());
            $this->assertSame($e->deleted_at->toDateString(), DB::table('attendances')->where('employee_id', $e->id)->max('work_date'));
        }
    }

    public function test_command_refuses_without_force_and_in_production(): void
    {
        Employee::factory()->create();

        $this->artisan('stress:seed', ['--employees' => 4])->assertFailed();
        $this->assertSame(1, Employee::count());

        $this->app['env'] = 'production';
        $this->artisan('stress:seed', ['--employees' => 4, '--force' => true])->assertFailed();
        $this->assertSame(1, Employee::count());
    }

    public function test_command_runs_with_force_and_skips_backup_on_memory_db(): void
    {
        $this->artisan('stress:seed', ['--employees' => 4, '--from' => '2026-10-01', '--to' => '2026-10-07', '--force' => true])
            ->expectsOutputToContain('Skipping backup')->assertSuccessful();
        $this->assertSame(4, Employee::withTrashed()->count());
    }

    public function test_same_seed_same_data(): void
    {
        $a = $this->gen(10, '2026-10-01', '2026-10-31');
        $name = Employee::orderBy('employee_code')->value('first_name');
        $b = $this->gen(10, '2026-10-01', '2026-10-31');

        $this->assertSame($a['attendances'], $b['attendances']);
        $this->assertSame($name, Employee::orderBy('employee_code')->value('first_name'));
    }

    protected function tearDown(): void
    {
        config(['database.default' => 'sqlite']); // so RefreshDatabase rolls back the right connection
        DB::purge('tmp');
        parent::tearDown();
    }

    private function tempDb(): string
    {
        $dir = sys_get_temp_dir().'/stress-'.uniqid();
        mkdir($dir);
        $file = "{$dir}/db.sqlite";
        touch($file);
        config(['database.connections.tmp' => ['driver' => 'sqlite', 'database' => $file, 'prefix' => '', 'foreign_key_constraints' => false]]);
        config(['database.default' => 'tmp']);
        DB::purge('tmp');
        DB::statement('create table employees (id integer primary key, name text)');
        DB::statement('create table attendances (id integer primary key)');
        DB::table('employees')->insert([['name' => 'a'], ['name' => 'b']]);

        return $file;
    }

    public function test_backup_is_a_verified_copy_with_the_rows(): void
    {
        $file = $this->tempDb();

        $backup = $this->app->make(StressSeed::class)->backup();

        $this->assertStringContainsString('.bak-stress-', $backup);
        $this->assertGreaterThan(0, filesize($backup));
        $copy = new \PDO("sqlite:{$backup}");
        $this->assertSame(2, (int) $copy->query('select count(*) from employees')->fetchColumn());
    }

    public function test_backup_failure_aborts_before_wipe(): void
    {
        $file = $this->tempDb();
        chmod(dirname($file), 0555);
        if (is_writable(dirname($file))) {
            chmod(dirname($file), 0755);
            $this->markTestSkipped('directory still writable (root?)');
        }

        try {
            $this->artisan('stress:seed', ['--employees' => 4, '--force' => true])->assertFailed();
            $this->assertSame(2, DB::table('employees')->count());
        } finally {
            chmod(dirname($file), 0755);
        }
    }

    public function test_command_rejects_bad_or_reversed_dates_before_wiping(): void
    {
        Employee::factory()->create();

        $this->artisan('stress:seed', ['--from' => '2026-13-45', '--force' => true])->assertFailed();
        $this->artisan('stress:seed', ['--to' => 'nope', '--force' => true])->assertFailed();
        $this->artisan('stress:seed', ['--from' => '2026-10-05', '--to' => '2026-10-01', '--force' => true])->assertFailed();
        $this->assertSame(1, Employee::count());
    }
}
