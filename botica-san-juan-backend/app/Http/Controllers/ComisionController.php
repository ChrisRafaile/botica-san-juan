<?php

namespace App\Http\Controllers;

use App\Models\Comision;
use App\Models\ComprobanteElectronico;
use Illuminate\Http\Request;

class ComisionController extends Controller
{
    public function index(Request $request)
    {
        $query = Comision::query()
            ->with('comprobante:id,serie,numero,tipo_comprobante,total,estado_sunat')
            ->orderByDesc('created_at');

        if ($request->filled('estado') && $request->input('estado') !== 'all') {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('tipo_agente') && $request->input('tipo_agente') !== 'all') {
            $query->where('tipo_agente', $request->input('tipo_agente'));
        }

        if ($request->filled('q')) {
            $search = trim((string) $request->input('q'));
            $query->where(function ($inner) use ($search) {
                $inner->where('agente_nombre', 'like', "%{$search}%")
                    ->orWhere('agente_documento', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 20);
        return $query->paginate(max(1, min($perPage, 100)));
    }

    public function registrar(Request $request, string $comprobanteId)
    {
        $comprobante = ComprobanteElectronico::findOrFail($comprobanteId);

        $validated = $request->validate([
            'tipo_agente' => 'required|in:medico,vendedor,referido',
            'agente_nombre' => 'required|string|max:255',
            'agente_documento' => 'nullable|string|max:20',
            'porcentaje' => 'required|numeric|min:0.1|max:100',
        ]);

        $monto = round(((float) $comprobante->total * (float) $validated['porcentaje']) / 100, 2);

        $comision = Comision::create([
            'comprobante_electronico_id' => $comprobante->id,
            'tipo_agente' => $validated['tipo_agente'],
            'agente_nombre' => trim($validated['agente_nombre']),
            'agente_documento' => $validated['agente_documento'] ?? null,
            'porcentaje' => $validated['porcentaje'],
            'monto' => $monto,
            'estado' => 'pendiente',
        ]);

        $comprobante->monto_comision = Comision::where('comprobante_electronico_id', $comprobante->id)->sum('monto');
        $comprobante->estado_comision = 'pendiente';
        $comprobante->save();

        return response()->json([
            'message' => 'Comision registrada correctamente.',
            'comision' => $comision,
            'comprobante' => $comprobante,
        ], 201);
    }

    public function liquidar(string $id)
    {
        $comision = Comision::with('comprobante')->findOrFail($id);
        $comision->estado = 'liquidada';
        $comision->fecha_liquidacion = now();
        $comision->save();

        $comprobante = $comision->comprobante;
        if ($comprobante) {
            $pending = Comision::where('comprobante_electronico_id', $comprobante->id)
                ->where('estado', 'pendiente')
                ->count();
            $comprobante->estado_comision = $pending > 0 ? 'pendiente' : 'liquidada';
            $comprobante->save();
        }

        return response()->json([
            'message' => 'Comision liquidada.',
            'comision' => $comision,
        ]);
    }
}
