<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MpUnidad extends Model
{
    use HasFactory;

    protected $table = 'mp_unidades';

    protected $fillable = ['nombre', 'abreviacion'];

    public function materiales()
    {
        return $this->hasMany(MpMaterial::class, 'unidad_id');
    }
}