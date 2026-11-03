<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdenCorte extends Model
{
    use HasFactory;

    protected $table = 'ordenes_corte';

    protected $fillable = [
        'code',
        'client',
        'selected_product',
        'status',
        'notes',
        'estimated_days',
        'progress',
    ];

    public function items()
    {
        return $this->hasMany(ItemOrdenCorte::class, 'cutting_order_id');
    }

    public function supplies()
    {
        return $this->hasMany(SuministroOrdenCorte::class, 'cutting_order_id');
    }
}
