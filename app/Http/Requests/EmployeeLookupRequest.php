<?php

namespace App\Http\Requests;

use App\Support\AttendanceDate;
use Illuminate\Foundation\Http\FormRequest;

class EmployeeLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('employee_code');
        $this->merge(['employee_code' => is_string($code) ? trim($code) : $code]);
    }

    public function rules(): array
    {
        return [
            'employee_code' => ['required', 'string', 'max:50'],
            'month' => ['nullable', 'date_format:Y-m', 'before_or_equal:'.AttendanceDate::thisMonth()],
        ];
    }
}
