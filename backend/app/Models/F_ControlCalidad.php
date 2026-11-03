<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class F_ControlCalidad extends Model
{
    use HasFactory;

    protected $table = 'controles_calidad';

    protected $fillable = [
        'orden_produccion_id',
        'fecha_inspeccion',
        'cantidad_aprobada',
        'cantidad_rechazada',
        'inspector_id',
        'observaciones',
    ];

    protected $casts = [
        'fecha_inspeccion' => 'date',
        'cantidad_aprobada' => 'integer',
        'cantidad_rechazada' => 'integer',
        'inspector_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con la orden de producción
     */
    public function ordenProduccion(): BelongsTo
    {
        return $this->belongsTo(F_OrdenProduccion::class, 'orden_produccion_id');
    }

    /**
     * Relación con el inspector (usuario)
     */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    /**
     * Relación con las reparaciones
     */
    public function reparaciones(): HasMany
    {
        return $this->hasMany(F_Reparacion::class, 'control_calidad_id');
    }

    /**
     * Calcular el total inspeccionado
     */
    public function getTotalInspeccionadoAttribute(): int
    {
        return $this->cantidad_aprobada + $this->cantidad_rechazada;
    }

    /**
     * Calcular el porcentaje de aprobación
     */
    public function getPorcentajeAprobacionAttribute(): float
    {
        $total = $this->getTotalInspeccionadoAttribute();
        return $total > 0 ? ($this->cantidad_aprobada / $total) * 100 : 0;
    }
}
