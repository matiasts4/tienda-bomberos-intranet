<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class F_MpStock extends Model
{
    use HasFactory;

    protected $table = 'mp_stocks';

    protected $fillable = [
        'especificacion_id',
        'cantidad_actual',
        'estado',
    ];

    protected $casts = [
        'cantidad_actual' => 'decimal:3',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con la especificación
     */
    public function especificacion(): BelongsTo
    {
        return $this->belongsTo(F_MpEspecificacion::class, 'especificacion_id');
    }
}
