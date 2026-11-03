<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evento extends Model
{
    protected $guarded = [];

    protected $casts = [
        'inicio' => 'datetime',
        'fin' => 'datetime',
        'is_all_day' => 'boolean',
        'metadata' => 'array',
        'alerta_enviada' => 'boolean',
    ];

    // Relación con el usuario (Creador/Dueño)
    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relación con los invitados al evento
    public function invitados() {
        return $this->belongsToMany(User::class, 'evento_user');
    }

    // Mantener ejecutivo() por retrocompatibilidad momentánea si es necesario
    public function ejecutivo() {
        return $this->belongsTo(User::class, 'ejecutivo_id');
    }

    // Relación Polimórfica: El evento puede pertenecer a cualquier cosa
    public function eventable() {
        return $this->morphTo();
    }

    // Relación específica para Oportunidad (antigua)
    public function oportunidad() {
        return $this->belongsTo(Oportunidad::class);
    }
}