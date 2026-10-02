<?php

namespace App\Http\Controllers;

use App\Models\Contacto;
use Illuminate\Http\Request;

class ContactoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Contacto::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre'   => 'required|string|max:255',
            'email'    => 'required|string|email|max:255',
            'telefono' => 'nullable|string|max:30',
            'motivo'   => 'nullable|string|max:255',
            'mensaje'  => 'required|string|max:5000',
        ]);

        /* telefono y motivo son NOT NULL en la tabla pero opcionales en el
           formulario: quien escribe no siempre deja telefono. Se guardan en
           blanco en vez de rechazar el mensaje, que es lo que de verdad
           importa recibir. La fecha la pone el servidor, no el navegador. */
        $contacto = Contacto::create([
            'nombre'   => $datos['nombre'],
            'email'    => $datos['email'],
            'telefono' => $datos['telefono'] ?? '',
            'motivo'   => $datos['motivo'] ?? 'Consulta general',
            'mensaje'  => $datos['mensaje'],
            'fecha'    => now(),
        ]);

        return response()->json($contacto, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $contacto = Contacto::findOrFail($id);
        return response()->json($contacto);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $contacto = Contacto::findOrFail($id);

        $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255',
            'mensaje' => 'sometimes|required|string',
        ]);

        $contacto->update($request->all());

        return response()->json($contacto);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $contacto = Contacto::findOrFail($id);
        $contacto->delete();

        return response()->json(['message' => 'Contacto deleted successfully']);
    }
}
