<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubcategoriaUpdateRequest extends FormRequest
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
            'categoria_id' => 'sometimes|required|integer|exists:categorias,id',
            'nombre' => 'sometimes|required|string|max:120',
            'descripcion' => 'nullable|string|max:255',
            'activa' => 'nullable|boolean',
        ];
    }
}
