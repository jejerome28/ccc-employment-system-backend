<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MINUTE_COLUMNS = [
        'work_minutes',
        'ot_minutes',
        'attended_minutes',
        'late_minutes',
        'early_minutes',
        'absent_minutes',
        'leave_minutes',
    ];

    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // "Person ID" on the biometric report.
            $table->string('biometric_id', 30)->nullable()->unique()->after('employee_code');
        });

        // Values are taken as-is from the biometric report; null on manually entered rows.
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('timetable', 80)->nullable()->after('time_out');
            // Composite report code, e.g. P, A, A-S, L-E.
            $table->string('status', 20)->nullable()->after('timetable');

            foreach (self::MINUTE_COLUMNS as $column) {
                $table->unsignedSmallInteger($column)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['timetable', 'status', ...self::MINUTE_COLUMNS]);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['biometric_id']);
            $table->dropColumn('biometric_id');
        });
    }
};
