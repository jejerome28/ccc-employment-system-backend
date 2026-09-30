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
        return [
            'clock_in_at' => ['nullable', 'date_format:Y-m-d\TH:i:s\Z'],
            'clock_out_at' => ['nullable', 'date_format:Y-m-d\TH:i:s\Z', ...($this->filled('clock_in_at') ? ['after:clock_in_at'] : [])],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
