<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CotizacionArchivo extends Model
{
    use HasFactory;

    protected $table = 'cotizacion_archivos';

    protected $fillable = [
        'cotizacion_id',
        'tipo',
        'nombre_archivo',
        'url',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id');
    }
}
