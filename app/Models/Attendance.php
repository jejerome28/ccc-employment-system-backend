<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'work_date',
        'time_in',
        'time_out',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Minutes between time in and time out. Null until the day is closed.
     * Handles an overnight shift (time out earlier on the clock than time in).
     */
    public function getWorkedMinutesAttribute(): ?int
    {
        if (! $this->time_in || ! $this->time_out) {
            return null;
        }

        $date = $this->work_date instanceof Carbon
            ? $this->work_date->toDateString()
            : (string) $this->work_date;

        $in = Carbon::parse("{$date} {$this->time_in}");
        $out = Carbon::parse("{$date} {$this->time_out}");

        if ($out->lessThan($in)) {
            $out->addDay();
        }

        return (int) $in->diffInMinutes($out);
    }

    public function getWorkedHoursAttribute(): ?float
    {
        $minutes = $this->worked_minutes;

        return $minutes === null ? null : round($minutes / 60, 2);
    }

    public function getDurationForHumansAttribute(): string
    {
        $minutes = $this->worked_minutes;

        if ($minutes === null) {
            return $this->time_in ? 'Still in' : '—';
        }

        return intdiv($minutes, 60) . 'h ' . str_pad($minutes % 60, 2, '0', STR_PAD_LEFT) . 'm';
    }

    public static function formatClock(?string $time): string
    {
        return $time ? Carbon::parse($time)->format('g:i A') : '—';
    }
}
