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
        'clock_in_at',
        'clock_out_at',
        'notes',
        'timetable',
        'status',
        'work_minutes',
        'ot_minutes',
        'attended_minutes',
        'late_minutes',
        'early_minutes',
        'absent_minutes',
        'leave_minutes',
    ];

    protected function casts(): array
    {
        return [
            'clock_in_at' => 'immutable_datetime',
            'clock_out_at' => 'immutable_datetime',
            'work_minutes' => 'integer',
            'ot_minutes' => 'integer',
            'attended_minutes' => 'integer',
            'late_minutes' => 'integer',
            'early_minutes' => 'integer',
            'absent_minutes' => 'integer',
            'leave_minutes' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getWorkedMinutesAttribute(): ?int
    {
        if ($this->attended_minutes !== null) {
            return $this->attended_minutes;
        }

        if (! $this->clock_in_at || ! $this->clock_out_at) {
            return null;
        }

        return (int) $this->clock_in_at->diffInMinutes($this->clock_out_at);
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

        return intdiv($minutes, 60).'h '.str_pad($minutes % 60, 2, '0', STR_PAD_LEFT).'m';
    }

    public static function formatClock(?string $time): string
    {
        return $time ? Carbon::parse($time)->format('g:i A') : '—';
    }
}
