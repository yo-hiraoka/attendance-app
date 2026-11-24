<?php

namespace App\Http\Requests\Admin;

use Laravel\Fortify\Http\Requests\LoginRequest;

class AdminLoginRequest extends LoginRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
