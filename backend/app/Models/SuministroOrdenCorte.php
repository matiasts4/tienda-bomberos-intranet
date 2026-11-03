<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuministroOrdenCorte extends Model
{
    use HasFactory;

    protected $table = 'suministros_ordenes_corte';

    protected $fillable = [
        'cutting_order_id',
        'name',
        'type',
        'quantity',
        'unit',
        'notes',
    ];

    public function ordenCorte()
    {
        return $this->belongsTo(OrdenCorte::class, 'cutting_order_id');
    }
}
