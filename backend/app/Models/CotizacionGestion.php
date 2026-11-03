<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CotizacionGestion extends Model
{
    use HasFactory;

    protected $table = 'cotizacion_gestiones';

    protected $fillable = [
        'cotizacion_id',
        'user_id',
        'glosa',
        'fecha_gestion',
        'fecha_vencimiento_nueva',
    ];

    protected $casts = [
        'fecha_gestion' => 'datetime',
        'fecha_vencimiento_nueva' => 'date',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
