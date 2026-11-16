<?php

namespace App\Http\Requests;

use App\Support\AttendanceDate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AttendanceExportRequest extends FormRequest
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
            'from' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.AttendanceDate::today()],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /** @return array{from: string, to: string} */
    public function range(): array
    {
        ['from' => $from, 'to' => $to] = $this->validated();

        return ['from' => $from, 'to' => min($to, AttendanceDate::today())];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $days = CarbonImmutable::parse($this->input('from'))->diffInDays(CarbonImmutable::parse($this->input('to'))) + 1;
                if ($days > 366) {
                    $validator->errors()->add('to', 'Range is at most 366 days.');
                }
            },
        ];
    }
}
