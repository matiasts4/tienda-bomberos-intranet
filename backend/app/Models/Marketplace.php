<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Marketplace extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'dealer_email',
        'quote_email',
        'internal_email',
        'password',
        'logo_url',
        'hero_image_url',
        'active',
        'primary_color',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'active' => 'boolean',
        'password' => 'hashed',
    ];

    public function products()
    {
        return $this->belongsToMany(MktProduct::class, 'marketplace_product', 'marketplace_id', 'product_id')
                    ->withPivot('is_visible')
                    ->withTimestamps();
    }

    public function quotes()
    {
        return $this->hasMany(MktQuote::class);
    }

    public function users()
    {
        return $this->hasMany(MktUser::class);
    }
}
