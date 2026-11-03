<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemOrdenCorte extends Model
{
    use HasFactory;

    protected $table = 'items_ordenes_corte';

    protected $fillable = [
        'cutting_order_id',
        'product_type',
        'size',
        'color',
        'quantity',
        'fabric_type',
        'notes',
    ];

    public function ordenCorte()
    {
        return $this->belongsTo(OrdenCorte::class, 'cutting_order_id');
    }

    public function cuttingOrder()
    {
        return $this->ordenCorte();
    }

    public function packages()
    {
        return $this->belongsToMany(Package::class, 'items_paquetes', 'cutting_order_item_id', 'package_id')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }
}
