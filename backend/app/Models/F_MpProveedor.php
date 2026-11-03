<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class F_MpProveedor extends Model
{
    use HasFactory;

    protected $table = 'mp_proveedores';

    protected $fillable = [
        'nombre_empresa',
        'rut_empresa',
        'fono',
        'email',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con las especificaciones
     */
    public function especificaciones(): HasMany
    {
        return $this->hasMany(F_MpEspecificacion::class, 'proveedor_id');
    }
}
