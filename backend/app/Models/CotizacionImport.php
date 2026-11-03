<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CotizacionImport extends Model
{
    protected $table = 'cotizacion_imports';

    protected $fillable = [
        'cliente_id',
        'oportunidad_id',
        'ejecutivo_id',
        'estado',
        'archivo_path',
        'error',
        'cotizacion_id',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id');
    }
}
