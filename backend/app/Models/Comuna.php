<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comuna extends Model
{
    protected $table = 'comunas';

    protected $fillable = ['nombre', 'region_id', 'provincia_id'];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }

    public function cuerpos(): HasMany
    {
        return $this->hasMany(Cuerpo::class, 'comuna_id');
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'comuna_id');
    }
}
