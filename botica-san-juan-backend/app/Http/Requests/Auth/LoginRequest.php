<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dni' => 'required|string|size:8',
            'password' => 'required|string',
            'mfa_code' => 'nullable|string|max:12',
        ];
    }
}
