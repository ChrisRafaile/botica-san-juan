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
        $request->validate([
            'nombre' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'mensaje' => 'required|string',
        ]);

        $contacto = Contacto::create($request->all());

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
