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
    ];

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
}
