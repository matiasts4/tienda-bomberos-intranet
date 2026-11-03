<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EncuestaQr extends Model
{
    protected $table = 'encuesta_qrs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'referencia_clave', 'origen', 'cotizacion_id', 'peticion_id', 'contacto_id',
        'cliente_id', 'referencia', 'nombre_venta', 'cliente_nombre', 'empresa_cliente',
        'vendedor_id', 'vendedor_nombre', 'generado_por_id', 'token', 'encuesta_id', 'respondida_at',
    ];

    protected $hidden = ['token'];

    protected $casts = ['respondida_at' => 'datetime'];

    public function generadoPor()
    {
        return $this->belongsTo(User::class, 'generado_por_id');
    }
}
