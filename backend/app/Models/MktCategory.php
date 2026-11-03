<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MktCategory extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function products()
    {
        return $this->hasMany(MktProduct::class, 'category_id');
    }

    public function parent()
    {
        return $this->belongsTo(MktCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(MktCategory::class, 'parent_id');
    }
}
