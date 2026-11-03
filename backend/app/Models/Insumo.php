<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Insumo extends Model
{
    protected $table = 'pte_insumos';

    protected $fillable = [
        'nombre',
        'descripcion' // in case it exists or we add it later, but seeder only had nombre
    ];
}
