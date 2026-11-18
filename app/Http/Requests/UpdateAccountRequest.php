<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'current_password' => ['required_with:password', 'nullable', 'string', 'current_password:sanctum'],
            'password' => ['required_with:current_password', 'nullable', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['nullable', 'string'],
        ];
    }
}
