<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'nombre' => 'required|string|max:255',
            'dni' => 'required|string|size:8|unique:usuarios',
            'email' => 'required|string|email|max:255|unique:usuarios',
            'password' => 'required|string|min:6',
            'telefono' => 'nullable|string|max:255',
            'acepta_terminos' => 'required|boolean',
            'foto_perfil' => 'nullable|string|max:255',
            'foto_portada' => 'nullable|string|max:255',
        ];
    }
}
