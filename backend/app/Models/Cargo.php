<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cargo extends Model
{
    protected $fillable = ['nombre'];

    public function contactos()
    {
        return $this->hasMany(Contacto::class, 'cargo_id');
    }
}
