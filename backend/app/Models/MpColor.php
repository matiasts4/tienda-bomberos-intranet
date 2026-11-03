<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MpColor extends Model
{
    use HasFactory;

    protected $table = 'mp_colores';

    protected $fillable = ['nombre', 'codigo_hex'];

    public function materiales()
    {
        return $this->hasMany(MpMaterial::class, 'color_id');
    }
}