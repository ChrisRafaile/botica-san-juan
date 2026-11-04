<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Receta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Recetas médicas.
 * ---------------------------------------------------------------------------
 * EN QUÉ SE DIFERENCIA ESTO DE LA FOTO DE PERFIL
 *
 * La tubería de subida se parece a la de CAM-012, pero hay una diferencia que
 * cambia el diseño entero: una foto de perfil no le importa a nadie y una
 * receta lleva el nombre del paciente, el del médico y, por el medicamento,
 * su diagnóstico.
 *
 * Tres consecuencias:
 *
 *   1. El archivo va al disco PRIVADO, no a storage/app/public. En el disco
 *      público bastaría adivinar la URL —o que alguien la reenvíe— para leer
 *      la receta de otra persona.
 *   2. Sale por un endpoint que comprueba quién pide. Lo ve su dueño y lo ve
 *      un administrador; nadie más, ni con el identificador correcto.
 *   3. El nombre del archivo lo genera Laravel. El que trae el cliente se
 *      guarda aparte, como dato, y nunca se usa para construir una ruta:
 *      «../../.env.pdf» es un nombre de archivo perfectamente válido.
 *
 * LO QUE ESTE CONTROLADOR NO HACE
 *
 * No decide si una receta es válida. No lee el archivo, no hace OCR, no
 * comprueba que el medicamento recetado sea el del pedido. Eso lo hace una
 * persona con título en el mostrador, y el sistema se limita a registrar
 * quién lo decidió y cuándo.
 */
class RecetaController extends Controller
{
    /** 8 MB. Una foto de móvil pasa de 4 con facilidad; un PDF escaneado, más. */
    private const MAX_KB = 8 * 1024;

    // --------------------------------------------------------------- cliente

    /**
     * Adjunta o sustituye la receta de un pedido.
     *
     * No admite `pedido_id` en el cuerpo: viene en la ruta y se comprueba
     * contra la cuenta en sesión. Aceptarlo del cuerpo permitiría adjuntar
     * una receta al pedido de otra persona.
     */
    public function subir(Request $request, Pedido $pedido): JsonResponse
    {
        $this->exigirAcceso($request, $pedido);

        $request->validate([
            'archivo' => [
                'required',
                File::types(['jpg', 'jpeg', 'png', 'webp', 'pdf'])
                    ->max(self::MAX_KB),
            ],
        ]);

        $subido = $request->file('archivo');

        /* La regla `types` ya mira el contenido, no la extensión. Esta segunda
           comprobación acota además QUÉ imágenes: sin ella entraría cualquier
           tipo que Laravel asocie a esas extensiones, y la pantalla del
           mostrador solo sabe pintar estos cuatro. */
        $mime = $subido->getMimeType();

        if (! in_array($mime, Receta::MIMES, true)) {
            return response()->json([
                'message' => 'El archivo no es una imagen ni un PDF que podamos mostrar.',
                'errors' => ['archivo' => ['Adjunta una foto (JPG, PNG o WEBP) o un PDF.']],
            ], 422);
        }

        $anterior = $pedido->receta;

        /* Una receta ya validada no se sustituye desde el portal. El
           boticario la miró y decidió; dejar que el cliente la cambie después
           convertiría esa decisión en provisional sin que nadie se entere. */
        if ($anterior && $anterior->estaValidada()) {
            return response()->json([
                'message' => 'Esta receta ya fue validada. Si necesitas cambiarla, avísanos en el mostrador.',
            ], 409);
        }

        $ruta = $subido->store(Receta::CARPETA, 'local');

        $receta = Receta::updateOrCreate(
            ['pedido_id' => $pedido->id],
            [
                'subida_por_id' => $request->user()->id,
                'archivo' => $ruta,
                /* Se guarda como dato para que el boticario vea qué mandó el
                   cliente, y se recorta: un nombre de 4 KB rompería la fila. */
                'nombre_original' => mb_substr((string) $subido->getClientOriginalName(), 0, 255),
                'tipo_mime' => $mime,
                'tamano_bytes' => $subido->getSize(),
                /* Vuelve a empezar: una receta nueva no hereda la decisión
                   tomada sobre la anterior. */
                'estado' => Receta::ESTADO_PENDIENTE,
                'motivo_rechazo' => null,
                'revisada_en' => null,
                'revisada_por_id' => null,
                'version' => $anterior ? $anterior->version + 1 : 1,
            ],
        );

        if ($anterior) {
            $this->borrarArchivo($anterior->getOriginal('archivo'));
        }

        return response()->json([
            'message' => 'Receta adjuntada. El químico farmacéutico la revisará antes de preparar tu pedido.',
            'receta' => $this->comoArreglo($receta),
        ], 201);
    }

    /** Estado de la receta de un pedido, para el portal y para el panel. */
    public function mostrar(Request $request, Pedido $pedido): JsonResponse
    {
        $this->exigirAcceso($request, $pedido);

        $receta = $pedido->receta()->with('revisadaPor:id,nombre')->first();

        return response()->json([
            'requiere_receta' => $pedido->requiereReceta(),
            'receta' => $receta ? $this->comoArreglo($receta) : null,
        ]);
    }

    /**
     * Entrega el archivo.
     *
     * Se transmite desde el disco privado en lugar de devolver una URL. Una
     * URL, aunque esté firmada, se puede reenviar; aquí cada petición vuelve a
     * pasar por la comprobación de quién pide.
     */
    public function archivo(Request $request, Pedido $pedido): StreamedResponse
    {
        $this->exigirAcceso($request, $pedido);

        $receta = $pedido->receta;

        abort_if($receta === null, 404, 'Este pedido no tiene receta adjunta.');
        abort_unless(Storage::disk('local')->exists($receta->archivo), 404, 'El archivo ya no está disponible.');

        /* `inline` para que el navegador lo muestre en vez de descargarlo, y
           el nombre que ve el usuario es el suyo, no el nombre generado. */
        return Storage::disk('local')->response(
            $receta->archivo,
            $receta->nombre_original,
            [
                'Content-Type' => $receta->tipo_mime,
                /* Un documento clínico no se queda en la caché del disco de un
                   equipo compartido del mostrador. */
                'Cache-Control' => 'no-store, private',
            ],
        );
    }

    // --------------------------------------------------------- administrador

    /** Cola de recetas por revisar. */
    public function pendientes(): JsonResponse
    {
        $recetas = Receta::porRevisar()
            ->with(['pedido:id,usuario_id,total,estado,fecha', 'subidaPor:id,nombre'])
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $recetas->map(fn (Receta $r) => $this->comoArreglo($r) + [
                'pedido_id' => $r->pedido_id,
                'cliente' => $r->subidaPor?->nombre,
            ]),
            'total' => Receta::where('estado', Receta::ESTADO_PENDIENTE)->count(),
        ]);
    }

    /**
     * Registra la decisión del profesional sobre la receta.
     *
     * Validar y rechazar comparten endpoint a propósito: son la misma acción
     * —alguien revisó y decidió— y separarlas invitaría a que una de las dos
     * olvidara dejar constancia de quién fue.
     */
    public function revisar(Request $request, Pedido $pedido): JsonResponse
    {
        $receta = $pedido->receta;

        abort_if($receta === null, 404, 'Este pedido no tiene receta adjunta.');

        $datos = $request->validate([
            'estado' => ['required', Rule::in([Receta::ESTADO_VALIDADA, Receta::ESTADO_RECHAZADA])],
            /* El motivo es obligatorio al rechazar. Un rechazo sin motivo deja
               al cliente adivinando qué tiene que repetir, y acaba en una
               segunda receta igual de inservible. */
            'motivo_rechazo' => [
                Rule::requiredIf(fn () => $request->input('estado') === Receta::ESTADO_RECHAZADA),
                'nullable', 'string', 'max:300',
            ],
        ]);

        $receta->forceFill([
            'estado' => $datos['estado'],
            'motivo_rechazo' => $datos['estado'] === Receta::ESTADO_RECHAZADA
                ? $datos['motivo_rechazo']
                : null,
            'revisada_en' => now(),
            'revisada_por_id' => $request->user()->id,
        ])->save();

        return response()->json([
            'message' => $datos['estado'] === Receta::ESTADO_VALIDADA
                ? 'Receta validada.'
                : 'Receta rechazada. El cliente verá el motivo y podrá adjuntar otra.',
            'receta' => $this->comoArreglo($receta->fresh(['revisadaPor'])),
        ]);
    }

    // ------------------------------------------------------------- auxiliares

    /**
     * Solo el dueño del pedido y el administrador.
     *
     * Exigir sesión no es exigir propiedad: ese fue exactamente el defecto
     * D-05 de las descargas de comprobantes, que dejó los documentos de todos
     * abiertos a cualquier cuenta autenticada. Aquí se comprueba las dos
     * cosas, y en un solo sitio para que no se pueda olvidar en una ruta.
     */
    private function exigirAcceso(Request $request, Pedido $pedido): void
    {
        $usuario = $request->user();

        if ($usuario->rol === 'administrador') {
            return;
        }

        /* Un pedido de mostrador sin cuenta asociada no pertenece a nadie en
           el portal: `null === null` daría acceso a cualquiera. */
        abort_if(
            $pedido->usuario_id === null || (int) $pedido->usuario_id !== (int) $usuario->id,
            403,
            'Este pedido no es tuyo.',
        );
    }

    /** Borra el archivo anterior, y solo si estaba donde debía. */
    private function borrarArchivo(?string $ruta): void
    {
        if (! $ruta || ! str_starts_with($ruta, Receta::CARPETA.'/')) {
            return;
        }

        Storage::disk('local')->delete($ruta);
    }

    /** @return array<string, mixed> */
    private function comoArreglo(Receta $receta): array
    {
        return [
            'id' => $receta->id,
            'estado' => $receta->estado,
            'nombre_original' => $receta->nombre_original,
            'tipo_mime' => $receta->tipo_mime,
            'es_imagen' => $receta->esImagen(),
            'tamano_bytes' => $receta->tamano_bytes,
            'version' => $receta->version,
            'motivo_rechazo' => $receta->motivo_rechazo,
            'revisada_en' => $receta->revisada_en,
            'revisada_por' => $receta->revisadaPor?->nombre,
            'created_at' => $receta->created_at,
            /* Ni la ruta ni una URL del archivo: se pide por su endpoint. */
        ];
    }
}
