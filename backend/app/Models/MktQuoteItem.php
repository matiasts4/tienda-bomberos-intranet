<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MktQuoteItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function quote()
    {
        return $this->belongsTo(MktQuote::class, 'quote_id');
    }

    public function product()
    {
        return $this->belongsTo(MktProduct::class, 'product_id');
    }
}
