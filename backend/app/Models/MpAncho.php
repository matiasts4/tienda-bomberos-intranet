<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MpAncho extends Model
{
    use HasFactory;

    protected $table = 'mp_anchos';

    protected $fillable = ['medida'];

    public function materiales()
    {
        return $this->hasMany(MpMaterial::class, 'ancho_id');
    }
}