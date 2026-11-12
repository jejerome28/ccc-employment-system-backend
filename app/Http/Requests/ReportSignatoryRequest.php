<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportSignatoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:300'],
        ];
    }
}
