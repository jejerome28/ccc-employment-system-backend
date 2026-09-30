<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClockRequest extends FormRequest
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
            'work_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now()->addDay()->toDateString()],
        ];
    }
}
