<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un cambio de ejecutivo responsable en una oportunidad o cotización. */
class TraspasoResponsable extends Model
{
    protected $table = 'traspasos_responsable';

    protected $fillable = [
        'model_type',
        'model_id',
        'de_user_id',
        'a_user_id',
        'por_user_id',
        'motivo',
    ];

    public function model()
    {
        return $this->morphTo();
    }

    public function de()
    {
        return $this->belongsTo(User::class, 'de_user_id');
    }

    public function a()
    {
        return $this->belongsTo(User::class, 'a_user_id');
    }

    public function por()
    {
        return $this->belongsTo(User::class, 'por_user_id');
    }
}
