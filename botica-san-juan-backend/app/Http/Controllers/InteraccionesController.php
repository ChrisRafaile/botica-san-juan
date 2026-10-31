<?php

namespace App\Http\Controllers;

use App\Services\Clinico\ConsultorDeInteracciones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Consulta de interacciones sobre una lista de productos.
 * ---------------------------------------------------------------------------
 * ES PUBLICO A PROPOSITO
 *
 * El carrito del portal funciona sin cuenta, así que exigir sesión aquí
 * dejaría sin aviso justo al visitante que compra sin registrarse. El
 * endpoint no lee ni escribe nada del cliente: recibe identificadores de
 * producto y devuelve lo que dice una tabla pública.
 *
 * Lleva limitador propio porque recorre el catálogo y la tabla de reglas.
 */
class InteraccionesController extends Controller
{
    public function __construct(private readonly ConsultorDeInteracciones $consultor)
    {
    }

    public function evaluar(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'productos' => ['required', 'array', 'min:1', 'max:50'],
            'productos.*' => ['integer'],
        ], [
            'productos.required' => 'Indica al menos un producto.',
            'productos.max' => 'Se revisan hasta 50 productos por consulta.',
        ]);

        $resultado = $this->consultor->evaluar($validado['productos']);

        /* El descargo viaja CON el resultado, no en una página aparte. Quien
           consuma esta API desde otro sitio recibe el aviso igual que quien
           usa el portal, y no puede mostrar el veredicto sin él. */
        $resultado['aviso_legal'] =
            'Esta revisión es orientativa y se basa en una tabla de interacciones '
            .'registradas en el sistema. No sustituye la evaluación de un profesional '
            .'de la salud. Ante cualquier duda, y siempre que haya tratamiento en curso, '
            .'consulta al químico farmacéutico de la botica.';

        return response()->json($resultado);
    }
}
