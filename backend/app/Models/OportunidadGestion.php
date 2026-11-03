<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OportunidadGestion extends Model
{
    use HasFactory;

    protected $table = 'oportunidad_gestiones';

    protected $fillable = [
        'oportunidad_id',
        'user_id',
        'tipo_contacto',
        'glosa',
        'fecha_gestion',
        'fecha_vencimiento_nueva',
    ];

    protected $casts = [
        'fecha_gestion' => 'datetime',
        'fecha_vencimiento_nueva' => 'date',
    ];

    public function oportunidad()
    {
        return $this->belongsTo(Oportunidad::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
