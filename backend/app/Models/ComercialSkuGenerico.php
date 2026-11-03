<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComercialSkuGenerico extends Model
{
    use HasFactory;

    protected $table = 'comercial_skus_genericos';

    protected $fillable = ['sku', 'nombre', 'imagen_url'];
}
