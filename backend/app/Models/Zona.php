<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zona extends Model
{
    protected $fillable = ['nombre', 'codigo'];

    public function regiones(): HasMany
    {
        return $this->hasMany(Region::class);
    }
}
