<?php

namespace App\Http\Requests;

use App\Support\AttendanceDate;
use Illuminate\Foundation\Http\FormRequest;

class EmployeeCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['month' => ['required', 'date_format:Y-m', 'before_or_equal:'.AttendanceDate::thisMonth()]];
    }
}
