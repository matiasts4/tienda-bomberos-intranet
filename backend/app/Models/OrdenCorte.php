<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de orden de corte con desglose de tallas, personalización institucional,
 * vínculo con cotización aprobada y trazabilidad de etapas de taller.
 */
class OrdenCorte extends Model
{
    use HasFactory;

    public const STAGE_CORTE = 'corte';
    public const STAGE_CONFECCION = 'confeccion';
    public const STAGE_EMPAQUE = 'empaque';
    public const STAGE_TERMINADO = 'terminado';

    /** @var array<int, string> */
    public const WORKSHOP_STAGES = [
        self::STAGE_CORTE,
        self::STAGE_CONFECCION,
        self::STAGE_EMPAQUE,
        self::STAGE_TERMINADO,
    ];

    /** @var array<string, string> */
    public const STAGE_LABELS = [
        self::STAGE_CORTE => 'Corte',
        self::STAGE_CONFECCION => 'Confección',
        self::STAGE_EMPAQUE => 'Empaque',
        self::STAGE_TERMINADO => 'Terminado',
    ];

    /** @var array<string, int> */
    public const STAGE_PROGRESS = [
        self::STAGE_CORTE => 25,
        self::STAGE_CONFECCION => 50,
        self::STAGE_EMPAQUE => 75,
        self::STAGE_TERMINADO => 100,
    ];

    protected $table = 'ordenes_corte';

    protected $fillable = [
        'code',
        'cotizacion_id',
        'cuerpo_id',
        'compania_id',
        'responsable_id',
        'client',
        'selected_product',
        'status',
        'notes',
        'estimated_days',
        'fecha_compromiso',
        'progress',
        'total_unidades',
        'unidades_en_taller',
        'unidades_en_reparacion',
        'unidades_listas',
    ];

    protected $casts = [
        'fecha_compromiso' => 'date:Y-m-d',
        'estimated_days' => 'integer',
        'total_unidades' => 'integer',
        'unidades_en_taller' => 'integer',
        'unidades_en_reparacion' => 'integer',
        'unidades_listas' => 'integer',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id');
    }

    public function cuerpo()
    {
        return $this->belongsTo(Cuerpo::class, 'cuerpo_id');
    }

    public function compania()
    {
        return $this->belongsTo(Compania::class, 'compania_id');
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function items()
    {
        return $this->hasMany(ItemOrdenCorte::class, 'cutting_order_id');
    }

    public function supplies()
    {
        return $this->hasMany(SuministroOrdenCorte::class, 'cutting_order_id');
    }

    public function historialEtapas()
    {
        return $this->morphMany(HistorialEtapa::class, 'model')
            ->with(['user:id,name,email', 'estadoAnterior', 'estadoNuevo'])
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function historial()
    {
        return $this->historialEtapas();
    }

    public function factoryLogs()
    {
        return $this->hasMany(FactoryLog::class, 'entity_id')
            ->where('entity_type', 'CuttingOrder')
            ->with('user:id,name,email')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function packages()
    {
        return $this->belongsToMany(Package::class, 'paquetes_ordenes_corte', 'cutting_order_id', 'package_id')
            ->withTimestamps();
    }
}
