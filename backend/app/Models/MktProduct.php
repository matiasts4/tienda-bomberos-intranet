<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MktProduct extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'images_json' => 'array',
        'variants_json' => 'array',
        'active' => 'boolean',
        'price' => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(MktCategory::class, 'category_id');
    }

    public function categories()
    {
        return $this->belongsToMany(MktCategory::class, 'mkt_category_product', 'product_id', 'category_id');
    }

    public function marketplaces()
    {
        return $this->belongsToMany(Marketplace::class, 'marketplace_product', 'product_id', 'marketplace_id')
                    ->withPivot('is_visible')
                    ->withTimestamps();
    }
}
