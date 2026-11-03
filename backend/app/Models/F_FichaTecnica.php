<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class F_FichaTecnica extends Model
{
    use HasFactory;

    protected $table = 'fichas_tecnicas';

    protected $fillable = [
        'sku',
        'materia_prima_id',
        'cantidad_requerida',
    ];

    protected $casts = [
        'cantidad_requerida' => 'decimal:3',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con el SKU del producto
     */
    public function sku(): BelongsTo
    {
        return $this->belongsTo(PteSku::class, 'sku', 'sku');
    }

    /**
     * Relación con la materia prima
     */
    public function materiaPrima(): BelongsTo
    {
        return $this->belongsTo(F_MpMateriaPrima::class, 'materia_prima_id');
    }
}
