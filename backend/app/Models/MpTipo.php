<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MpTipo extends Model
{
    use HasFactory;

    protected $table = 'mp_tipos';

    protected $fillable = ['nombre'];

    // Relación inversa: Un tipo tiene muchos materiales
    public function materiales()
    {
        return $this->hasMany(MpMaterial::class, 'tipo_id');
    }
}