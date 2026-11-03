<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Cuenta (compañía, o los contactos directos de un cuerpo) que no se vuelve a
 * llamar hasta una fecha: lo pidió alguien de la cuenta ("ya le compramos a
 * otro", "no hay presupuesto este año"). Mientras está activa, ninguno de sus
 * contactos aparece en la cola.
 */
class ProspeccionPausa extends Model
{
    protected $table = 'prospeccion_pausas';

    protected $fillable = ['compania_id', 'cuerpo_id', 'hasta', 'motivo', 'user_id', 'gestion_id', 'levantada_at'];

    protected $casts = [
        'hasta' => 'date',
        'levantada_at' => 'datetime',
    ];

    /** Pausas vigentes hoy: no levantadas y con fecha de término hoy o después. */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->whereNull('levantada_at')->whereDate('hasta', '>=', Carbon::today());
    }

    public function compania()
    {
        return $this->belongsTo(Compania::class);
    }

    public function cuerpo()
    {
        return $this->belongsTo(Cuerpo::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
