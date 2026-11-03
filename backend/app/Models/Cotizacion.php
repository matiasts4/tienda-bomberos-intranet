<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Support\DeliveryDeadline;
use App\Models\Concerns\TieneDestinatario;
use Illuminate\Database\Eloquent\Model;

class Cotizacion extends Model
{
    use HasFactory, TieneDestinatario;

    /** Relaciones que acompañan a la tarjeta para mostrar creador, responsable e historial de traspasos. */
    public const RELACIONES_RESPONSABLE = [
        'ejecutivo:id,name',
        'creador:id,name',
        'traspasos',
    ];

    protected $table = 'cotizaciones';

    protected $fillable = [
        'oportunidad_id',
        'cliente_id',
        'contacto_id',
        'cuerpo_id',
        'compania_id',
        'user_id',
        'creado_por_id',
        'estado_id',
        'numero',
        'nombre',
        'fecha_creacion',
        'fecha_vencimiento',
        'fecha_venta',
        'observaciones',
        'total_neto',
        'iva',
        'total',
        'plazo_produccion',
        'condiciones_pago',
        'despacho',
        'personalizacion_completada',
        'origen',
        'fecha_rechazo',
        'motivo_rechazo',
        'motivo_no_vigente',
        'motivo_no_vigente_otro',
        'observacion_no_vigente',
        'fecha_no_vigente',
        'no_vigente_por_id',
        'estado_personalizacion',
    ];

    protected $casts = [
        'fecha_creacion' => 'date',
        'fecha_vencimiento' => 'date',
        'fecha_venta' => 'date:Y-m-d',
        'fecha_rechazo' => 'datetime',
        'fecha_no_vigente' => 'datetime',
        'personalizacion_completada' => 'boolean',
    ];

    protected $appends = [
        'nombre_cliente',
        'fecha_entrega',
        'tipo_destinatario',
    ];

    /** Fecha límite de entrega: fecha_venta + plazo_produccion. */
    public function getFechaEntregaAttribute(): ?string
    {
        return DeliveryDeadline::fechaEntrega($this->fecha_venta, $this->plazo_produccion);
    }

    public function getNombreClienteAttribute(): string
    {
        $cuerpo = $this->cuerpo ?? $this->compania?->cuerpo;
        if ($cuerpo) {
            return "Cuerpo de Bomberos de {$cuerpo->nombre}" . ($this->compania ? " – {$this->compania->nombre}" : "");
        }
        if ($this->cliente) {
            return $this->cliente->nombre_empresa ?? 'Cliente no especificado';
        }
        if ($this->contacto) {
            return $this->contacto->nombre ?? 'Cliente no especificado';
        }
        return 'Cliente no especificado';
    }

    public function detalles()
    {
        return $this->hasMany(DetalleCotizacion::class, 'cotizacion_id');
    }

    public function despachoModelo()
    {
        return $this->hasOne(Despacho::class, 'cotizacion_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function contacto()
    {
        return $this->belongsTo(Contacto::class, 'contacto_id');
    }

    public function cuerpo()
    {
        return $this->belongsTo(Cuerpo::class, 'cuerpo_id');
    }

    public function compania()
    {
        return $this->belongsTo(Compania::class, 'compania_id');
    }

    public function oportunidad()
    {
        return $this->belongsTo(Oportunidad::class, 'oportunidad_id');
    }

    public function ejecutivo()
    {
        return $this->belongsTo(User::class, 'user_id');
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

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'estado_id');
    }

    /** Quien la movió a "No Vigente". */
    public function noVigentePor()
    {
        return $this->belongsTo(User::class, 'no_vigente_por_id');
    }

    public function historial()
    {
        return $this->morphMany(HistorialEtapa::class, 'model')
            ->with(['user', 'estadoAnterior', 'estadoNuevo'])
            ->latest();
    }

    public function archivos()
    {
        return $this->hasMany(CotizacionArchivo::class, 'cotizacion_id');
    }

    public function gestiones()
    {
        return $this->hasMany(CotizacionGestion::class)->orderByDesc('fecha_gestion');
    }

    public function personalizaciones()
    {
        return $this->hasMany(Personalizacion::class, 'cotizacion_id');
    }

    public function ultimaGestion()
    {
        return $this->hasOne(CotizacionGestion::class)->latestOfMany();
    }
}
