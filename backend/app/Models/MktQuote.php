<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MktQuote extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function marketplace()
    {
        return $this->belongsTo(Marketplace::class);
    }

    public function items()
    {
        return $this->hasMany(MktQuoteItem::class, 'quote_id');
    }
}
