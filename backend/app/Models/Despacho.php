<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Despacho extends Model
{
    protected $table = 'despachos';

    protected $fillable = [
        'cotizacion_id',
        'cliente_id',
        'estado_id',
        'calle',
        'numero_calle',
        'comuna',
        'ciudad',
        'agencia',
        'voucher_url',
        'fecha_despacho',
        'observacion',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'estado_id');
    }

    public function historial()
    {
        return $this->morphMany(HistorialEtapa::class, 'model')
            ->with(['user', 'estadoAnterior', 'estadoNuevo'])
            ->latest();
    }
}
