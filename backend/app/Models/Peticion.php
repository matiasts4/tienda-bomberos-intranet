<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Peticion extends Model
{
    protected $table = 'peticiones';

    protected $fillable = [
        'detalle_cotizacion_id',
        'estado_id',
        'user_id',
        'sku',
        'cantidad',
        'cotizacion_id',
        'referencia_externa',
        'nombre_cotizacion',
        'cliente_externo',
        'fecha_creacion',
        'fecha_vencimiento',
        'observacion'
    ];

    public function detalleCotizacion()
    {
        return $this->belongsTo(DetalleCotizacion::class, 'detalle_cotizacion_id');
    }

    public function skuProducto()
    {
        return $this->belongsTo(PteSku::class, 'sku', 'sku');
    }

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id');
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

}
