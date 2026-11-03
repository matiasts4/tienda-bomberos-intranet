<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class F_MpEspecificacion extends Model
{
    use HasFactory;

    protected $table = 'mp_especificaciones';

    protected $fillable = [
        'materia_prima_id',
        'proveedor_id',
        'fecha_ingreso',
        'lote_proveedor',
        'ancho_real',
        'gramaje',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'ancho_real' => 'decimal:2',
        'gramaje' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con la materia prima
     */
    public function materiaPrima(): BelongsTo
    {
        return $this->belongsTo(F_MpMateriaPrima::class, 'materia_prima_id');
    }

    /**
     * Relación con el proveedor
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(F_MpProveedor::class, 'proveedor_id');
    }

    /**
     * Relación con los stocks
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(F_MpStock::class, 'especificacion_id');
    }
}
