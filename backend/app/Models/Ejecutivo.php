<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ejecutivo extends Model
{
    public function cotizaciones()
{
    return $this->hasMany(Cotizacion::class);
}

public function oportunidades()
{
    return $this->hasMany(Oportunidad::class);
}

}
