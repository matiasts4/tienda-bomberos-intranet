<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EncuestaSatisfaccion extends Model
{
    use HasFactory;

    protected $fillable = [
        'cotizacion_id',
        'cliente_id',
        'calidad_producto',
        'calidad_personalizacion',
        'tiempo_entrega',
        'atencion_comercial',
        'comentarios',
        'identificador_venta',
        'vendedor_id',
        'vendedor_manual',
        'nombre_cliente_manual',
        'nombre_venta',
        'origen',
        'referencia_clave',
        'peticion_id',
        'contacto_id',
        'empresa_cliente',
        'vendedor_nombre',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function vendedor()
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function peticion()
    {
        return $this->belongsTo(Peticion::class);
    }

    public function contacto()
    {
        return $this->belongsTo(Contacto::class);
    }
}
