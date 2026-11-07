<?php

namespace App\Http\Requests;

use Closure;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => [
                'required', 'date_format:Y-m-d',
                function (string $attribute, mixed $value, Closure $fail) {
                    $from = $this->input('from');
                    if (! is_string($from) || ! is_string($value)) {
                        return;
                    }
                    $from = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
                    $to = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

                    if (! $from || ! $to) {
                        return;
                    }
                    if ($to < $from) {
                        $fail('The end date must be on or after the start date.');
                    } elseif ($from->diff($to)->days >= 31) {
                        $fail('The range can be at most 31 days.');
                    }
                },
            ],
        ];
    }
}
