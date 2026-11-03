<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class F_OrdenProduccion extends Model
{
    use HasFactory;

    protected $table = 'ordenes_produccion';

    protected $fillable = [
        'peticion_id',
        'sku',
        'cantidad_a_producir',
        'estado_produccion',
    ];

    protected $casts = [
        'cantidad_a_producir' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con la petición
     */
    public function peticion(): BelongsTo
    {
        return $this->belongsTo(Peticion::class);
    }

    /**
     * Relación con el SKU del producto
     */
    public function sku(): BelongsTo
    {
        return $this->belongsTo(PteSku::class, 'sku', 'sku');
    }

    /**
     * Relación con los controles de calidad
     */
    public function controlesCalidad(): HasMany
    {
        return $this->hasMany(F_ControlCalidad::class, 'orden_produccion_id');
    }

    /**
     * Relación con las reparaciones
     */
    public function reparaciones(): HasMany
    {
        return $this->hasMany(F_Reparacion::class, 'orden_produccion_id');
    }
}
