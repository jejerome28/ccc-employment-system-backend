<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRequest extends FormRequest
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
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'work_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now()->addDay()->toDateString()],
            'clock_in_at' => ['nullable', 'date_format:Y-m-d\TH:i:s\Z'],
            'clock_out_at' => ['nullable', 'date_format:Y-m-d\TH:i:s\Z', ...$this->afterClockIn()],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return list<string> */
    private function afterClockIn(): array
    {
        return $this->filled('clock_in_at') ? ['after:clock_in_at'] : [];
    }
}
