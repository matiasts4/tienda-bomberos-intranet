<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PteSku extends Model
{
    use HasFactory;

    // 1. CONFIGURACIÓN DE LA TABLA
    protected $table = 'pte_skus';      // Nombre exacto en la BD
    protected $primaryKey = 'sku';      // La llave no es 'id', es 'sku'
    public $incrementing = false;       // El SKU no es autoincrementable (1,2,3...)
    protected $keyType = 'string';      // El SKU es texto (para respetar ceros a la izquierda)
    protected $guarded = [];            // Permitimos guardar todos los campos sin restricciones
    protected $casts = [
        'activo' => 'boolean',
    ];

    // ── RELACIÓN CON EL PRODUCTO PADRE (GENÉRICO) ──
    public function skuGenerico()
    {
        return $this->belongsTo(PteSkuGenerico::class, 'sku_generico_id');
    }

    // ── RELACIÓN CON EL INVENTARIO (BODEGA) ──
    // Un Producto tiene UN registro de stock
    public function stock()
    {
        return $this->hasOne(PteStock::class, 'sku', 'sku');
    }

    // Un Producto tiene MUCHOS movimientos en su historia
    public function movimientos()
    {
        return $this->hasMany(PteMovimiento::class, 'sku', 'sku');
    }

    // ── ATRIBUTOS PROPIOS DE LA VARIANTE ──
    public function color()
    {
        return $this->belongsTo(PteColor::class, 'color_id');
    }

    public function talla()
    {
        return $this->belongsTo(PteTalla::class, 'talla_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
