<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CategoriaUpdateRequest extends FormRequest
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
        $id = (string) ($this->route('id') ?? $this->route('categoria') ?? '');

        return [
            'nombre' => 'sometimes|required|string|max:120|unique:categorias,nombre,' . $id,
            'descripcion' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:40',
            'activa' => 'nullable|boolean',
        ];
    }
}
