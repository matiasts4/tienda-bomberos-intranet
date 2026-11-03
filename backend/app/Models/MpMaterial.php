<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MpMaterial extends Model
{
    use HasFactory;
    
    protected $table = 'mp_materiales';

    protected $fillable = [
        'nombre_base', 'codigo_interno', 'tipo_id', 
        'unidad_id', 'ancho_id', 'color_id', 
        'stock_minimo', 'descripcion', 'activo'
    ];

    // --- Relaciones con Catálogos ---
    public function tipo()   { return $this->belongsTo(MpTipo::class, 'tipo_id'); }
    public function unidad() { return $this->belongsTo(MpUnidad::class, 'unidad_id'); }
    public function ancho()  { return $this->belongsTo(MpAncho::class, 'ancho_id'); }
    public function color()  { return $this->belongsTo(MpColor::class, 'color_id'); }

    // --- Relaciones Principales ---
    
    // 1. Stock Físico (Lotes/Rollos)
    public function lotes()
    {
        return $this->hasMany(MpLote::class, 'material_id');
    }

    // 2. Proveedores (Quién me lo vende y a cuánto)
    public function proveedores()
    {
        return $this->belongsToMany(MpProveedor::class, 'mp_proveedor_productos', 'material_id', 'proveedor_id')
                    ->withPivot('sku_proveedor', 'precio_referencia', 'moneda')
                    ->withTimestamps();
    }

    // --- Atributo Virtual (Stock Total Real) ---
    // Permite llamar a $material->stock_total y saber cuánto hay en total sumando todos los rollos
    public function getStockTotalAttribute()
    {
        return $this->lotes()->where('estado', 'DISPONIBLE')->sum('cantidad_actual');
    }
}
?>