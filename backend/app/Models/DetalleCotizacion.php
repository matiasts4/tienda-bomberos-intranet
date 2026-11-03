<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetalleCotizacion extends Model
{
    use HasFactory;

    protected $table = 'detalle_cotizaciones';

    protected $fillable = [
        'cotizacion_id',
        'sku',
        'n_item',
        'cantidad',
        'subtotal',
        'is_personalizable',
        'producto',
        'talla',
        'color',
        'genero',
        'tipo_personalizacion',
        'precio_unitario',
        'total_neto',
        'configuracion',
    ];

    protected $casts = [
        'is_personalizable' => 'boolean',
        'configuracion' => 'array',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id');
    }

    public function skuGenerico()
    {
        return $this->belongsTo(ComercialSkuGenerico::class, 'sku', 'sku');
    }
}
