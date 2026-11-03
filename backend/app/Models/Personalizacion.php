<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Personalizacion extends Model
{
    use SoftDeletes;

    protected $table = 'personalizaciones';

    protected $fillable = [
        'usuario_creador_id',
        'movimiento_inventario_id',
        'cotizacion_id',
        'sku',
        'nombre_producto',
        'cantidad',
        'estado',
        'tipo',
        'siguiente_proceso',
        'proceso_previo',
        'numero_item',
        'prioridad',
        'notas',
        'fecha_inicio_proceso',
        'fecha_inicio_qc',
        'personalizacion_padre_id',
        'bloqueado_qc',
        'ciclos_qc',
        'operario_asignado_id',
        'configuracion',
    ];

    protected $casts = [
        'bloqueado_qc'         => 'boolean',
        'ciclos_qc'            => 'integer',
        'fecha_inicio_proceso' => 'datetime',
        'fecha_inicio_qc'      => 'datetime',
        'configuracion'        => 'array',
    ];

    // ── Relaciones ───────────────────────────────────────────────────────────

    public function usuarioCreador()
    {
        return $this->belongsTo(User::class, 'usuario_creador_id');
    }

    public function operarioAsignado()
    {
        return $this->belongsTo(User::class, 'operario_asignado_id');
    }

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function movimientoInventario()
    {
        return $this->belongsTo(PteMovimiento::class, 'movimiento_inventario_id');
    }

    public function archivos()
    {
        return $this->hasMany(PersonalizacionArchivo::class, 'personalizacion_id');
    }

    public function variantes()
    {
        return $this->hasMany(PersonalizacionVariante::class, 'personalizacion_id');
    }

    // Clones generados por un split de QC
    public function clones()
    {
        return $this->hasMany(Personalizacion::class, 'personalizacion_padre_id');
    }

    // Original del que proviene este clon
    public function padre()
    {
        return $this->belongsTo(Personalizacion::class, 'personalizacion_padre_id');
    }
}