<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalizacionVariante extends Model
{
    protected $table = 'personalizacion_variantes';

    protected $fillable = [
        'personalizacion_id',
        'talla',
        'color',
        'genero',
        'cantidad',
    ];

    public function personalizacion()
    {
        return $this->belongsTo(Personalizacion::class);
    }
}
