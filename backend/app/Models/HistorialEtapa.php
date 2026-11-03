<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistorialEtapa extends Model
{
    protected $table = 'historial_etapas';

    protected $fillable = [
        'model_type',
        'model_id',
        'user_id',
        'estado_anterior_id',
        'estado_nuevo_id',
        'observaciones',
    ];

    public function model()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function estadoAnterior()
    {
        return $this->belongsTo(Estado::class, 'estado_anterior_id');
    }

    public function estadoNuevo()
    {
        return $this->belongsTo(Estado::class, 'estado_nuevo_id');
    }
}
