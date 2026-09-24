<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Producto extends Model
{
    protected $table = 'productos';

    protected $fillable = [
        'nombre',
        'concentracion',
        'adicional',
        'laboratorio',
        'presentacion',
        'unidad_base',
        'venta_fraccionada',
        'unidades_por_blister',
        'blisters_por_caja',
        'tipo',
        'categoria_id',
        'subcategoria_id',
        'stock',
        'stock_minimo',
        'stock_reposicion',
        'precio',
        'precio_blister',
        'precio_caja',
        'imagen',
        'codigo_barras',
        'codigo_digemid',
        'principio_activo',
        'requiere_receta',
        'laboratorio_fabricante',
        'afecto_igv',
        'tipo_afectacion_igv',
        'base_legal_exoneracion',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'precio_blister' => 'decimal:2',
        'precio_caja' => 'decimal:2',
        'stock' => 'integer',
        'unidades_por_blister' => 'integer',
        'blisters_por_caja' => 'integer',
        'stock_minimo' => 'integer',
        'stock_reposicion' => 'integer',
        'venta_fraccionada' => 'boolean',
        'requiere_receta' => 'boolean',
        'afecto_igv' => 'boolean',
    ];

    /* ======================================================================
       Afectación al IGV
       ======================================================================
       La presentación comercial —unidad, blíster o caja— NO interviene aquí.
       Es el mismo medicamento: cambia cuánto se entrega y a qué precio, no si
       la operación está gravada. El tratamiento tributario es del producto y
       se aplica igual a las tres formas de venta.
    */

    /** Catálogo 07 de la SUNAT, reducido a lo que una botica necesita. */
    public const GRAVADO   = '10';
    public const EXONERADO = '20';
    public const INAFECTO  = '30';

    public const TIPOS_AFECTACION = [
        self::GRAVADO   => 'Gravado',
        self::EXONERADO => 'Exonerado',
        self::INAFECTO  => 'Inafecto',
    ];

    /**
     * Por qué un producto no paga IGV.
     *
     * Se guarda la base legal, no sólo el hecho, porque la lista de
     * medicamentos exonerados la actualiza el MINSA cada año por Decreto
     * Supremo: cuando salga la nueva, hay que saber qué productos revisar.
     */
    public const BASES_LEGALES = [
        /* Verificado contra el texto oficial del TUO (sunat.gob.pe, literal A
           del Apéndice I): NO contiene ni una sola partida del capítulo 30,
           el de productos farmacéuticos. Son productos agrícolas frescos,
           fertilizantes, lana, algodón, oro y algunos vehículos. Para una
           botica sólo aplicaría si vendiera alimentos frescos. */
        'apendice_i' => 'Apéndice I del TUO de la Ley del IGV · bienes exonerados, sin medicamentos',

        /* Aquí es donde está la exoneración de medicamentos. La relación de
           principios activos la aprueba el MINSA por Decreto Supremo y se
           actualiza cada año, por eso hay que anotar cuál aplica. */
        'ley_27450'  => 'Ley 27450 · medicamentos oncológicos y para VIH/SIDA',
        'ley_28553'  => 'Ley 28553 · medicamentos para diabetes',
        'otra'       => 'Otra norma (detallar con el contador)',
    ];

    public function estaGravado(): bool
    {
        return $this->tipo_afectacion_igv === self::GRAVADO;
    }

    public function estaExonerado(): bool
    {
        return $this->tipo_afectacion_igv === self::EXONERADO;
    }

    public function esInafecto(): bool
    {
        return $this->tipo_afectacion_igv === self::INAFECTO;
    }

    public function etiquetaAfectacion(): string
    {
        return self::TIPOS_AFECTACION[$this->tipo_afectacion_igv] ?? 'Gravado';
    }

    public function scopeExonerados($query)
    {
        return $query->where('tipo_afectacion_igv', self::EXONERADO);
    }

    public function scopeGravados($query)
    {
        return $query->where('tipo_afectacion_igv', self::GRAVADO);
    }

    /**
     * Mantiene `afecto_igv` como espejo de la afectación.
     *
     * La columna booleana sigue existiendo porque hay consultas y pantallas
     * que la usan. Dejar que las dos se editen por separado sería pedir que se
     * contradigan: aquí la fuente de verdad es `tipo_afectacion_igv` y la otra
     * se deriva, nunca al revés.
     */
    protected static function booted(): void
    {
        static::saving(function (self $producto) {
            $tipo = $producto->tipo_afectacion_igv ?? self::GRAVADO;

            if (! array_key_exists($tipo, self::TIPOS_AFECTACION)) {
                $tipo = self::GRAVADO;
                $producto->tipo_afectacion_igv = $tipo;
            }

            $producto->afecto_igv = $tipo === self::GRAVADO;

            /* Un producto gravado no arrastra base legal de exoneración: si
               dejó de estar exonerado, el motivo ya no aplica. */
            if ($tipo === self::GRAVADO) {
                $producto->base_legal_exoneracion = null;
            }
        });
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function subcategoria(): BelongsTo
    {
        return $this->belongsTo(Subcategoria::class, 'subcategoria_id');
    }

    public function digemidCatalogo(): BelongsTo
    {
        return $this->belongsTo(DigemidCatalogo::class, 'codigo_digemid', 'codigo_digemid');
    }

    public function carritos()
    {
        return $this->hasMany(Carrito::class, 'producto_id');
    }

    public function pedidoDetalles()
    {
        return $this->hasMany(PedidoDetalle::class, 'producto_id');
    }

    public function lotes()
    {
        return $this->hasMany(Lote::class, 'producto_id');
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoStock::class, 'producto_id');
    }

    public function incidencias()
    {
        return $this->hasMany(IncidenciaVenta::class, 'producto_id');
    }
}
