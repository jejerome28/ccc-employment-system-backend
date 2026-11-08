<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'role' => ['sometimes', 'required', Rule::enum(Role::class)],
            'password' => ['nullable', 'string', 'min:8'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $target = $this->route('user');
            $role = $this->input('role');

            if ($role !== null && $target->role === Role::Admin && $role !== Role::Admin->value
                && User::where('role', Role::Admin)->count() === 1) {
                $validator->errors()->add('role', 'At least one admin is required.');
            }
        }];
    }
}
