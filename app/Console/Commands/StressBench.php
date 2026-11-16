<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\User;
use App\Services\BiometricReportImporter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StressBench extends Command
{
    protected $signature = 'stress:bench {--runs=3} {--out=storage/app/stress-results.md}';

    protected $description = 'Time the key API requests in-process against the current database';

    public function handle(BiometricReportImporter $importer): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run in production.');

            return self::FAILURE;
        }
        $admin = User::where('role', 'admin')->orderBy('id')->first();
        $employee = Employee::orderBy('id')->first();
        if (! $admin || ! $employee) {
            $this->error('Need an admin user and at least one employee.');

            return self::FAILURE;
        }
        $id = $employee->id;
        $runs = max(1, (int) $this->option('runs'));

        // [label, uri, target seconds, target MB|null]
        $cases = [
            ['1 GET /api/dashboard', '/api/dashboard', 2],
            ['2 GET /api/employees (page=1, page=46, q=santos, group=cos_teaching, status=inactive, archived=1; slowest)', 'EMPLOYEES', 2],
            ['3 GET /api/attendance?date=2026-11-12', '/api/attendance?date=2026-11-12', 2],
            ['4 GET /api/employees/{id}?month=2026-11', "/api/employees/{$id}?month=2026-11", 2],
            ['5 GET /api/employees/{id}/calendar?month=2026-11', "/api/employees/{$id}/calendar?month=2026-11", 2],
            ['6 GET /api/attendance/summary 11-01..11-12', '/api/attendance/summary?from=2026-11-01&to=2026-11-12', 2],
            ['7 GET /api/attendance/summary 10-01..10-31', '/api/attendance/summary?from=2026-10-01&to=2026-10-31', 3],
            ['8 GET /api/attendance/summary/export 10-01..10-31', '/api/attendance/summary/export?from=2026-10-01&to=2026-10-31', 15, 256],
            ['9 GET /api/attendance/summary/cra 10-01..10-31', '/api/attendance/summary/cra?from=2026-10-01&to=2026-10-31', 15, 256],
            ['10 GET /api/attendance/export 10-01..10-31', '/api/attendance/export?from=2026-10-01&to=2026-10-31', 15, 256],
        ];

        $results = [];
        $get = function (string $uri) use ($admin) {
            return function () use ($admin, $uri) {
                Sanctum::actingAs($admin);
                $response = app(Kernel::class)->handle(Request::create($uri, 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']));
                if ($response instanceof BinaryFileResponse) {
                    ob_start();
                    $response->sendContent();
                    ob_end_clean();
                    $file = $response->getFile()->getPathname();
                    is_file($file) && unlink($file);
                }

                return $response->getStatusCode();
            };
        };
        foreach ($cases as $case) {
            [$label, $uri, $sec] = $case;
            $mb = $case[3] ?? null;
            if ($uri !== 'EMPLOYEES') {
                $results[] = $this->measure($label, $runs, $sec, $mb, $get($uri));

                continue;
            }
            $variants = array_map(fn ($q) => $this->measure($label, $runs, $sec, $mb, $get("/api/employees?{$q}")),
                ['page=1', 'page=46', 'q=santos', 'group=cos_teaching', 'status=inactive', 'archived=1']);
            usort($variants, fn ($a, $b) => $b[1] <=> $a[1]);
            $slow = $variants[0];
            $slow[4] = collect($variants)->every(fn ($v) => $v[4] === 'yes') ? 'yes' : (collect($variants)->contains(fn ($v) => $v[4] === 'ERR') ? 'ERR' : 'NO');
            $results[] = $slow;
        }

        [$path, $size] = $this->importCsv();
        $results[] = $this->measure("11 POST /api/attendance/import 10-01..10-15 ({$size} KB csv, direct importer)", $runs, 30, null, function () use ($importer, $path) {
            DB::beginTransaction();
            try {
                ['rows' => $rows, 'errors' => $errors] = $importer->parse($path);
                if ($errors || ! $rows) {
                    return 422;
                }
                $importer->save($rows);

                return 200;
            } finally {
                DB::rollBack();
            }
        });
        unlink($path);

        $lines = ['| # | Request | Median ms | Peak MB | Target | Pass |', '|---|---|---|---|---|---|'];
        foreach ($results as [$label, $ms, $peak, $target, $pass]) {
            [$n, $req] = explode(' ', $label, 2);
            $lines[] = "| {$n} | {$req} | {$ms} | {$peak} | {$target} | ".$pass.' |';
        }
        $table = implode("\n", $lines)."\n";
        $this->line($table);

        $out = $this->option('out');
        $out = str_starts_with($out, '/') ? $out : base_path($out);
        file_put_contents($out, $table);

        return collect($results)->contains(fn ($r) => $r[4] === 'ERR') ? self::FAILURE : self::SUCCESS;
    }

    /** @return array{string, float, float, string, string} last is yes|NO|ERR (ERR = a request was not 200) */
    private function measure(string $label, int $runs, int $sec, ?int $mb, callable $call): array
    {
        $times = [];
        $peak = 0;
        $ok = true;
        for ($i = 0; $i < $runs; $i++) {
            memory_reset_peak_usage();
            $t = hrtime(true);
            $status = $call();
            $times[] = (hrtime(true) - $t) / 1e6;
            $peak = max($peak, memory_get_peak_usage(true) / 1048576);
            $ok = $ok && $status === 200;
        }
        sort($times);
        $ms = round($times[intdiv(count($times), 2)], 1);
        $peak = round($peak, 1);
        $target = "< {$sec} s".($mb ? ", < {$mb} MB" : '');
        $pass = ! $ok ? 'ERR' : ($ms < $sec * 1000 && (! $mb || $peak < $mb) ? 'yes' : 'NO');

        return [$label, $ms, $peak, $target, $pass];
    }

    /** @return array{string, int} temp csv path and size in KB */
    private function importCsv(): array
    {
        $path = tempnam(sys_get_temp_dir(), 'bench');
        $fh = fopen($path, 'w');
        fputcsv($fh, ['Seq', 'Person ID', 'Name', 'Department', 'Position', 'Gender', 'Date', 'Weekday', 'Timetable', 'Check-In', 'Check-out', 'Work', 'OT', 'Attended', 'Late', 'Early', 'Absent', 'Leave', 'Status', 'Records']);
        $tz = config('attendance.timezone');
        $seq = 0;
        $q = DB::table('attendances')->join('employees', 'employees.id', '=', 'attendances.employee_id')
            ->whereBetween('work_date', ['2026-10-01', '2026-10-15'])->whereNotNull('biometric_id')
            ->select('attendances.id', 'biometric_id', 'last_name', 'first_name', 'work_date', 'clock_in_at', 'clock_out_at', 'attendances.status');
        foreach ($q->lazyById(5000, 'attendances.id', 'id') as $a) {
            $t = fn ($v) => $v ? CarbonImmutable::parse($v, 'UTC')->setTimezone($tz)->format('H:i:s') : '-';
            fputcsv($fh, [++$seq, $a->biometric_id, "{$a->last_name} {$a->first_name}", '-', '-', '-', substr($a->work_date, 0, 10), '-', 'Default Timetable', $t($a->clock_in_at), $t($a->clock_out_at), 0, 0, 0, 0, 0, 0, 0, $a->status ?: 'P', '-']);
        }
        fclose($fh);

        return [$path, (int) round(filesize($path) / 1024)];
    }
}
