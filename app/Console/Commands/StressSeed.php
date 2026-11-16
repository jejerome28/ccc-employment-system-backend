<?php

namespace App\Console\Commands;

use App\Services\StressDataGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StressSeed extends Command
{
    protected $signature = 'stress:seed {--employees=548} {--from=2021-11-13} {--to=2026-11-12} {--seed=42} {--force}';

    protected $description = 'Wipe employees/attendances and generate bulk stress-test data';

    public function handle(StressDataGenerator $generator): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run in production.');

            return self::FAILURE;
        }
        if (! $this->option('force')) {
            $this->error('This wipes employees and attendances. Re-run with --force.');

            return self::FAILURE;
        }

        $from = $this->option('from');
        $to = $this->option('to');
        foreach ([$from, $to] as $d) {
            if (! is_string($d) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || date('Y-m-d', strtotime($d)) !== $d) {
                $this->error('--from and --to must be valid Y-m-d dates.');

                return self::FAILURE;
            }
        }
        if ($from > $to) {
            $this->error('--from must be on or before --to.');

            return self::FAILURE;
        }

        try {
            $backup = $this->backup();
        } catch (\Throwable $e) {
            $this->error('Backup failed, nothing was wiped: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->line($backup ? "Backup: {$backup}" : 'Skipping backup (not a sqlite file database).');

        $bar = $this->output->createProgressBar((int) $this->option('employees'));
        $result = $generator->run(
            (int) $this->option('employees'), $from, $to,
            (int) $this->option('seed'), fn () => $bar->advance(),
        );
        $bar->finish();
        $this->newLine();
        $this->info("Seeded {$result['employees']} employees, {$result['attendances']} attendances.");

        return self::SUCCESS;
    }

    /** Verified VACUUM INTO copy of the sqlite file; null when there is no file to back up. */
    public function backup(): ?string
    {
        if (DB::getDriverName() !== 'sqlite') {
            return null;
        }
        // The path SQLite actually opened (handles relative paths and DB_URL).
        $file = DB::selectOne('PRAGMA database_list')->file ?? '';
        if ($file === '') {
            return null;
        }

        $backup = $file.'.bak-stress-'.date('Ymd-His');
        DB::statement('VACUUM INTO '.DB::getPdo()->quote($backup));
        clearstatcache(true, $backup);
        if (! is_file($backup) || filesize($backup) === 0) {
            throw new \RuntimeException("backup file {$backup} missing or empty");
        }

        return $backup;
    }
}
