<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CategoriaStoreRequest extends FormRequest
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
            'nombre' => 'required|string|max:120|unique:categorias,nombre',
            'descripcion' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:40',
            'activa' => 'nullable|boolean',
        ];
    }
}
