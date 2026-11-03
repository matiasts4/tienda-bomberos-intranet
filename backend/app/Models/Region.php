<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    protected $table = 'regiones'; // Explicitly set table name since Laravel assumes 'regions'

    protected $fillable = ['nombre', 'zona_id'];

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    public function provincias(): HasMany
    {
        return $this->hasMany(Provincia::class, 'region_id');
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'region_id');
    }
}
