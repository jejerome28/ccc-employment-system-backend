<?php

namespace Tests\Unit;

use App\Services\AttendanceCalculator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class AttendanceCalculatorTest extends TestCase
{
    private const MON = '2026-11-09';

    private function at(string $hms, string $date = self::MON): CarbonImmutable
    {
        return CarbonImmutable::parse("{$date} {$hms}", 'Asia/Manila')->utc();
    }

    private function day(string $date, ?string $in, ?string $out, ?string $remark = null, ?string $part = null): array
    {
        return app(AttendanceCalculator::class)->day(
            $date,
            $in ? $this->at($in, $date) : null,
            $out ? $this->at($out, $date) : null,
            $remark,
            $part,
        );
    }

    public function test_on_time_full_day(): void
    {
        $d = $this->day(self::MON, '07:00:00', '18:00:00');

        $this->assertSame('present', $d['status']);
        $this->assertSame(1.0, $d['present']);
        $this->assertSame(0.0, $d['absent']);
        $this->assertSame(0, $d['late_minutes']);
        $this->assertSame(0, $d['undertime_minutes']);
        $this->assertSame(0, $d['ot_minutes']);
    }

    public function test_grace_boundary(): void
    {
        $this->assertSame(0, $this->day(self::MON, '07:15:59', '18:00:00')['late_minutes']);
        $this->assertSame(1, $this->day(self::MON, '07:16:00', '18:00:00')['late_minutes']);
        $this->assertSame(30, $this->day(self::MON, '07:45:00', '18:00:00')['late_minutes']);
    }

    public function test_undertime_and_ot(): void
    {
        $this->assertSame(120, $this->day(self::MON, '07:00:00', '16:00:00')['undertime_minutes']);

        $late = $this->day(self::MON, '07:00:00', '19:30:00');
        $this->assertSame(90, $late['ot_minutes']);
        $this->assertSame(0, $late['undertime_minutes']);
    }

    public function test_no_punches_is_absent(): void
    {
        $d = $this->day(self::MON, null, null);

        $this->assertSame('absent', $d['status']);
        $this->assertSame(1.0, $d['absent']);
        $this->assertSame(0.0, $d['present']);
    }

    public function test_friday_and_weekend_are_off(): void
    {
        foreach (['2026-11-13', '2026-11-14'] as $date) {
            $d = $this->day($date, null, null);
            $this->assertSame('off', $d['status']);
            $this->assertSame(0.0, $d['absent']);
        }

        $friday = $this->day('2026-11-13', '07:40:00', '15:00:00');
        $this->assertSame('off', $friday['status']);
        $this->assertSame(0, $friday['late_minutes']);
        $this->assertSame(0, $friday['undertime_minutes']);
    }

    public function test_holiday_is_off(): void
    {
        config(['attendance.holidays' => [self::MON => 'Test']]);

        $this->assertSame('off', $this->day(self::MON, null, null)['status']);
    }

    public function test_single_punch_needs_check(): void
    {
        $d = $this->day(self::MON, '07:05:00', null);
        $this->assertSame('needs_check', $d['status']);
        $this->assertSame(0.0, $d['absent']);
        $this->assertSame(0, $d['late_minutes']);
        $this->assertSame(0, $d['undertime_minutes']);

        $this->assertSame('needs_check', $this->day(self::MON, '07:05:00', '07:05:00')['status']);
    }

    public function test_whole_day_remark(): void
    {
        $ob = $this->day(self::MON, null, null, 'ob', 'whole');
        $this->assertSame('remark', $ob['status']);
        $this->assertSame(1.0, $ob['ob']);
        $this->assertSame(0.0, $ob['absent']);

        $this->assertSame(1.0, $this->day(self::MON, null, null, 'leave', null)['leave']);
    }

    public function test_morning_remark_moves_start(): void
    {
        $d = $this->day(self::MON, '13:16:00', '18:00:00', 'ob', 'am');

        $this->assertSame(0.5, $d['ob']);
        $this->assertSame(0.5, $d['present']);
        $this->assertSame(1, $d['late_minutes']);
    }

    public function test_afternoon_remark_moves_end(): void
    {
        $d = $this->day(self::MON, '07:00:00', '11:00:00', 'ob', 'pm');

        $this->assertSame(60, $d['undertime_minutes']);
        $this->assertSame(0.5, $d['ob']);
        $this->assertSame(0.5, $d['present']);
    }

    public function test_half_remark_without_punches(): void
    {
        $d = $this->day(self::MON, null, null, 'leave', 'pm');

        $this->assertSame(0.5, $d['leave']);
        $this->assertSame(0.5, $d['absent']);
    }
}
