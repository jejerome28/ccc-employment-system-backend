<?php

namespace App\Services;

use App\Enums\EmploymentGroup;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Generates deterministic bulk employees + attendance rows for load testing. */
class StressDataGenerator
{
    private const FIRST = ['Maria', 'Jose', 'Juan', 'Ana', 'Pedro', 'Luz', 'Carlo', 'Grace', 'Mark', 'Joy', 'Rico', 'Liza', 'Noel', 'Rhea', 'Ramon', 'Cathy', 'Dante', 'Mylene', 'Arnel', 'Jenny'];

    private const LAST = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres', 'Villanueva', 'Ramos', 'Aquino', 'Castro', 'Flores', 'Navarro', 'Dela Cruz', 'Del Rosario', 'Pascual', 'Soriano', 'Lim', 'Tan', 'Manalo', 'Corpuz', 'Salazar'];

    /** @return array{employees: int, attendances: int} */
    public function run(int $employees, string $from, string $to, int $seed, ?callable $progress = null): array
    {
        mt_srand($seed);
        $sqlite = DB::getDriverName() === 'sqlite' && DB::transactionLevel() === 0; // pragmas can't change inside a (test) transaction
        if ($sqlite) {
            $journal = DB::selectOne('PRAGMA journal_mode')->journal_mode ?? 'delete';
            $sync = DB::selectOne('PRAGMA synchronous')->synchronous ?? 2;
            DB::statement('PRAGMA journal_mode = WAL');
            DB::statement('PRAGMA synchronous = OFF');
        }

        try {
            return $this->generate($employees, $from, $to, $progress);
        } finally {
            if ($sqlite) {
                foreach (["journal_mode = {$journal}", "synchronous = {$sync}"] as $pragma) {
                    try {
                        DB::statement("PRAGMA {$pragma}");
                    } catch (\Throwable) {
                        // keep restoring the rest; don't mask the original exception
                    }
                }
            }
        }
    }

    private function generate(int $n, string $from, string $to, ?callable $progress): array
    {
        $tz = config('attendance.timezone');
        $days = [];
        for ($d = CarbonImmutable::parse($from, $tz); $d->toDateString() <= $to; $d = $d->addDay()) {
            $date = $d->toDateString();
            $days[] = [
                $date,
                $d->utc()->timestamp, // Manila midnight as UTC epoch
                in_array($d->isoWeekday(), config('attendance.workdays'), true)
                    && ! array_key_exists($date, config('attendance.holidays')),
            ];
        }

        DB::transaction(function () {
            DB::table('attendances')->delete();
            DB::table('employees')->delete();
        });

        // Employee plan.
        $groups = EmploymentGroup::cases();
        $archiveCount = $n === 548 ? 6 : max(1, (int) round($n * 0.01));
        $step = max(1, intdiv($n, $archiveCount));
        $archived = [];
        for ($k = 0; $k < $archiveCount; $k++) {
            $archived[($k * $step + 5) % $n] = CarbonImmutable::parse('2026-10-05')->addDays(mt_rand(0, 20))->toDateString();
        }
        $now = now()->toDateTimeString();
        $rows = [];
        for ($i = 0; $i < $n; $i++) {
            $midRange = ! isset($archived[$i]) && $i % 20 === 7 && count($days) > 1;
            $hire = $midRange ? $days[mt_rand(1, count($days) - 1)][0] : $from;
            $rows[] = [
                'employee_code' => sprintf('STR-%04d', $i + 1),
                'biometric_id' => (string) (2001 + $i),
                'first_name' => self::FIRST[$i % count(self::FIRST)],
                'last_name' => self::LAST[intdiv($i, count(self::FIRST)) % count(self::LAST)].($i >= 460 ? ' '.chr(65 + $i % 26) : ''),
                'email' => 'stress'.($i + 1).'@example.com',
                'phone' => null,
                'position' => 'Staff',
                'department' => 'Registrar',
                'hire_date' => $hire,
                'status' => $i % 50 === 3 ? 'inactive' : 'active',
                'employment_group' => $groups[$i % count($groups)]->value,
                'deleted_at' => isset($archived[$i]) ? $archived[$i].' 00:00:00' : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('employees')->insert($chunk);
        }
        $ids = DB::table('employees')->orderBy('employee_code')->pluck('id')->all();

        $total = 0;
        foreach ($rows as $i => $emp) {
            $last = isset($archived[$i]) ? min($to, $archived[$i]) : $to;
            $batch = [];
            foreach ($days as [$date, $base, $work]) {
                if ($date < $emp['hire_date']) {
                    continue;
                }
                if ($date > $last) {
                    break;
                }
                $batch[] = $this->row($ids[$i], $date, $base, $work, $now);
                if (count($batch) === 2000) {
                    $total += $this->flush($batch);
                    $batch = [];
                }
            }
            $total += $this->flush($batch);
            if ($progress) {
                $progress($i + 1, $n);
            }
        }

        return ['employees' => $n, 'attendances' => $total];
    }

    private function flush(array $batch): int
    {
        if ($batch) {
            DB::transaction(fn () => DB::table('attendances')->insert($batch));
        }

        return count($batch);
    }

    private function row(int $employeeId, string $date, int $base, bool $work, string $now): array
    {
        $in = $out = $remark = $part = null;
        if ($work) {
            $rand = fn (int $fromMin, int $toMin) => $base + mt_rand($fromMin, $toMin) * 60 + mt_rand(0, 59);
            $fmt = fn (int $ts) => gmdate('Y-m-d H:i:s', $ts);
            $normalIn = fn () => $fmt($rand(405, 435));          // 06:45-07:15
            $normalOut = fn () => $fmt($rand(1080, 1100));       // 18:00-18:20
            $r = mt_rand(1, 100);
            if ($r <= 78) {
                [$in, $out] = [$normalIn(), $normalOut()];
            } elseif ($r <= 84) {
                [$in, $out] = [$fmt($rand(436, 510)), $normalOut()];     // 07:16-08:30
            } elseif ($r <= 88) {
                [$in, $out] = [$normalIn(), $fmt($rand(900, 1079))];     // 15:00-17:59
            } elseif ($r <= 92) {
                [$in, $out] = [$normalIn(), $fmt($rand(1110, 1260))];    // 18:30-21:00
            } elseif ($r <= 95) {
                // absent
            } elseif ($r <= 97) {
                $remark = mt_rand(0, 1) ? 'ob' : 'leave';
                $part = 'whole';
            } elseif ($r === 98) {
                $remark = mt_rand(0, 1) ? 'ob' : 'leave';
                if (mt_rand(0, 1)) {
                    $part = 'am';
                    [$in, $out] = [$fmt($rand(775, 790)), $normalOut()]; // pm half punched
                } else {
                    $part = 'pm';
                    [$in, $out] = [$normalIn(), $fmt($rand(720, 730))];  // am half punched
                }
            } else {
                $in = $normalIn(); // needs check: in only
            }
        }

        return [
            'employee_id' => $employeeId, 'work_date' => $date,
            'clock_in_at' => $in, 'clock_out_at' => $out,
            'remark' => $remark, 'remark_part' => $part,
            'created_at' => $now, 'updated_at' => $now,
        ];
    }
}
