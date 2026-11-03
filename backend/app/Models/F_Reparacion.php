<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class F_Reparacion extends Model
{
    use HasFactory;

    protected $table = 'reparaciones';

    protected $fillable = [
        'control_calidad_id',
        'orden_produccion_id',
        'materia_prima_id',
        'cantidad_perdida',
        'motivo_reparacion',
    ];

    protected $casts = [
        'cantidad_perdida' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con el control de calidad
     */
    public function controlCalidad(): BelongsTo
    {
        return $this->belongsTo(F_ControlCalidad::class, 'control_calidad_id');
    }

    /**
     * Relación con la orden de producción
     */
    public function ordenProduccion(): BelongsTo
    {
        return $this->belongsTo(F_OrdenProduccion::class, 'orden_produccion_id');
    }

    /**
     * Relación con la materia prima
     */
    public function materiaPrima(): BelongsTo
    {
        return $this->belongsTo(F_MpMateriaPrima::class, 'materia_prima_id');
    }
}
