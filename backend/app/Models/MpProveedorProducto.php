<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class MpProveedorProducto extends Pivot
{
    // Indicar explícitamente la tabla (Laravel a veces se confunde con los plurales)
    protected $table = 'mp_proveedor_productos';

    // Importante: Si vas a usar 'created_at' y 'updated_at' en el pivote
    public $incrementing = true;

    protected $fillable = [
        'proveedor_id',
        'material_id',
        'sku_proveedor',     // Ej: "TEL-555-AZ"
        'precio_referencia', // Ej: 4500
        'moneda'             // CLP
    ];

    protected $casts = [
        'precio_referencia' => 'decimal:2',
    ];

    // Relaciones hacia arriba (por si consultas directo a esta tabla)
    public function material()
    {
        return $this->belongsTo(MpMaterial::class, 'material_id');
    }

    public function proveedor()
    {
        return $this->belongsTo(MpProveedor::class, 'proveedor_id');
    }
}