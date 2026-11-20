<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Desglose de prenda, talla, color, tela y personalización dentro de una orden de corte.
 */
class ItemOrdenCorte extends Model
{
    use HasFactory;

    protected $table = 'items_ordenes_corte';

    protected $fillable = [
        'cutting_order_id',
        'detalle_cotizacion_id',
        'sku',
        'product_type',
        'size',
        'color',
        'quantity',
        'fabric_type',
        'personalizacion',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'personalizacion' => 'array',
    ];

    public function ordenCorte()
    {
        return $this->belongsTo(OrdenCorte::class, 'cutting_order_id');
    }

    public function cuttingOrder()
    {
        return $this->ordenCorte();
    }

    public function detalleCotizacion()
    {
        return $this->belongsTo(DetalleCotizacion::class, 'detalle_cotizacion_id');
    }

    public function packages()
    {
        return $this->belongsToMany(Package::class, 'items_paquetes', 'cutting_order_item_id', 'package_id')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }
}
