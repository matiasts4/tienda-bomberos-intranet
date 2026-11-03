<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalizacionArchivo extends Model
{
    protected $table = 'personalizacion_archivos';

    protected $fillable = [
        'personalizacion_id',
        'tipo_archivo',
        'confirmado_fisico',
        'url_archivo',
    ];

    protected $casts = [
        'confirmado_fisico' => 'boolean',
    ];

    public function personalizacion()
    {
        return $this->belongsTo(Personalizacion::class);
    }
}
