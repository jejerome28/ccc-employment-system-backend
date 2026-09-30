<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dateTime('clock_in_at')->nullable()->after('work_date');
            $table->dateTime('clock_out_at')->nullable()->after('clock_in_at');
        });

        $tz = config('attendance.timezone');

        DB::table('attendances')->orderBy('id')->each(function (object $row) use ($tz) {
            $date = substr($row->work_date, 0, 10);
            $in = $row->time_in ? CarbonImmutable::parse("{$date} {$row->time_in}", $tz) : null;
            $out = $row->time_out ? CarbonImmutable::parse("{$date} {$row->time_out}", $tz) : null;

            if ($in && $out && $out->lessThan($in)) {
                $out = $out->addDay();
            }

            DB::table('attendances')->where('id', $row->id)->update([
                'clock_in_at' => $in?->utc()->format('Y-m-d H:i:s'),
                'clock_out_at' => $out?->utc()->format('Y-m-d H:i:s'),
            ]);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['time_in', 'time_out']);
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->time('time_in')->nullable()->after('work_date');
            $table->time('time_out')->nullable()->after('time_in');
        });

        $tz = config('attendance.timezone');
        $local = fn (?string $utc) => $utc ? CarbonImmutable::parse($utc, 'UTC')->setTimezone($tz)->format('H:i:s') : null;

        DB::table('attendances')->orderBy('id')->each(function (object $row) use ($local) {
            DB::table('attendances')->where('id', $row->id)->update([
                'time_in' => $local($row->clock_in_at),
                'time_out' => $local($row->clock_out_at),
            ]);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['clock_in_at', 'clock_out_at']);
        });
    }
};
