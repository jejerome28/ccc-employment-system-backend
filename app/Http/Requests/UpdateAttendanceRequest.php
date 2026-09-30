<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $format = 'Y-m-d\TH:i:s\Z';
        $attendance = $this->route('attendance');
        $storedIn = $attendance->clock_in_at?->format($format);
        $storedOut = $attendance->clock_out_at?->format($format);

        return [
            'clock_in_at' => ['nullable', 'date_format:'.$format, ...(! $this->has('clock_out_at') && $storedOut ? ['before:'.$storedOut] : [])],
            'clock_out_at' => ['nullable', 'date_format:'.$format, ...($this->filled('clock_in_at') ? ['after:clock_in_at'] : (! $this->has('clock_in_at') && $storedIn ? ['after:'.$storedIn] : []))],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
