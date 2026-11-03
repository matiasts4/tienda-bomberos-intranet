<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\TieneDestinatario;
use Illuminate\Database\Eloquent\Model;

class Oportunidad extends Model
{
    use HasFactory, TieneDestinatario;

    /** Relaciones que acompañan a la tarjeta para mostrar creador, responsable e historial de traspasos. */
    public const RELACIONES_RESPONSABLE = [
        'ejecutivo:id,name',
        'creador:id,name',
        'traspasos',
    ];

    protected $table = 'oportunidades';

    protected $fillable = [
        'fecha_ingreso',
        'referido_por',
        'canal',
        'mensaje_prospeccion',
        'cliente_id',
        'contacto_id',
        'cuerpo_id',
        'compania_id',
        'user_id',
        'creado_por_id',
        'estado_id',
    ];


    protected $casts = [
        'fecha_ingreso' => 'date',
    ];

    protected $appends = ['tipo_destinatario'];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function contacto()
    {
        return $this->belongsTo(Contacto::class);
    }

    public function cuerpo()
    {
        return $this->belongsTo(Cuerpo::class);
    }

    public function compania()
    {
        return $this->belongsTo(Compania::class);
    }

    /** Quien creó la tarjeta; no cambia aunque se traspase la responsabilidad. */
    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    /** Ejecutivo responsable o gestionador actual (= user_id). */
    public function responsable()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function traspasos()
    {
        return $this->morphMany(TraspasoResponsable::class, 'model')
            ->with(['de:id,name', 'a:id,name', 'por:id,name'])
            ->latest('id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ejecutivo()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }

    public function cotizaciones()
    {
        return $this->hasMany(Cotizacion::class);
    }

    public function gestiones()
    {
        return $this->hasMany(OportunidadGestion::class);
    }

    public function ultima_gestion()
    {
        return $this->hasOne(OportunidadGestion::class)->latestOfMany('fecha_gestion');
    }

    public function historial()
    {
        return $this->morphMany(HistorialEtapa::class, 'model')
            ->with(['user', 'estadoAnterior', 'estadoNuevo'])
            ->latest();
    }
}
