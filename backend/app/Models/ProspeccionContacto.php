<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProspeccionContacto extends Model
{
    use HasFactory;

    protected $table = 'prospeccion_contactos';

    protected $fillable = [
        'contacto_id',
        'user_id',
        'tipo_id',
        'canal',
        'notas',
        'proxima_llamada',
        'hora',
        'objetivo',
        'motivo',
        'intento',
        'derivado_a_id',
        'datos',
    ];

    protected $casts = [
        'proxima_llamada' => 'date',
        'intento' => 'integer',
        'datos' => 'array',
    ];

    public function contacto()
    {
        return $this->belongsTo(Contacto::class);
    }

    public function tipo()
    {
        return $this->belongsTo(ProspeccionTipo::class, 'tipo_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Contacto al que se derivó la conversación ("habla con el capitán"). */
    public function derivadoA()
    {
        return $this->belongsTo(Contacto::class, 'derivado_a_id');
    }
}
