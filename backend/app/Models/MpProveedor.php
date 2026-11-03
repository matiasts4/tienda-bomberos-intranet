<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MpProveedor extends Model
{
    use HasFactory;

    protected $table = 'mp_proveedores';

    protected $fillable = [
        'nombre_fantasia',
        'razon_social',
        'rut_empresa',
        'contacto_nombre',
        'telefono',
        'email'
    ];

    // Relación: Un proveedor suministra muchos materiales
    // Accedemos a través de la tabla pivote mp_proveedor_productos
    public function materiales()
    {
        return $this->belongsToMany(MpMaterial::class, 'mp_proveedor_productos', 'proveedor_id', 'material_id')
                    ->withPivot('sku_proveedor', 'precio_referencia', 'moneda')
                    ->withTimestamps();
    }

    // Lotes de mercadería asociados a este proveedor (para validación de eliminación)
    public function lotes()
    {
        return $this->hasMany(MpLote::class, 'proveedor_id');
    }
}