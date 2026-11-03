<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProspeccionTipo extends Model
{
    protected $table = 'prospeccion_tipos';

    protected $fillable = [
        'slug',
        'nombre',
        'color',
        'icono',
        'score',
        'es_final',
    ];

    protected $casts = [
        'es_final' => 'boolean',
        'score' => 'integer',
    ];

    public function interacciones()
    {
        return $this->hasMany(ProspeccionContacto::class, 'tipo_id');
    }
}
