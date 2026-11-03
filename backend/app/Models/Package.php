<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;

    protected $table = 'paquetes';

    protected $fillable = [
        'code',
        'destination',
        'transport_type',
        'priority',
        'status',
        'workshop',
        'workshop_notes',
        'notes',
        'estimated_delivery_days',
        'shipped_date',
        'delivered_date',
        'tracking_number',
        'total_unidades',
        'unidades_en_taller',
        'unidades_en_reparacion',
        'unidades_listas',
        'parent_package_id',
        'is_repair_package',
    ];

    protected $casts = [
        'is_repair_package' => 'boolean',
        'shipped_date' => 'date',
        'delivered_date' => 'date',
        'total_unidades' => 'integer',
        'unidades_en_taller' => 'integer',
        'unidades_en_reparacion' => 'integer',
        'unidades_listas' => 'integer',
    ];

    /**
     * Relación con items de orden de corte
     */
    public function items()
    {
        return $this->belongsToMany(ItemOrdenCorte::class, 'items_paquetes', 'package_id', 'cutting_order_item_id')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }

    /**
     * Relación con las órdenes de corte
     */
    public function cuttingOrders()
    {
        return $this->belongsToMany(OrdenCorte::class, 'paquetes_ordenes_corte', 'package_id', 'cutting_order_id')
                    ->withTimestamps();
    }

    /**
     * Paquete padre (para reparaciones)
     */
    public function parentPackage()
    {
        return $this->belongsTo(Package::class, 'parent_package_id');
    }

    /**
     * Paquetes hijos de reparación
     */
    public function repairPackages()
    {
        return $this->hasMany(Package::class, 'parent_package_id');
    }

    /**
     * Generar siguiente código automático
     */
    public static function generateNextCode()
    {
        $lastPackage = self::orderBy('id', 'desc')->first();
        if (!$lastPackage) {
            return 'P-001';
        }
        $lastCode = $lastPackage->code; // e.g. 'P-001'
        $number = intval(substr($lastCode, 2)) + 1;
        return 'P-' . str_pad($number, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Progreso de QC en porcentaje
     */
    public function getQcProgressPercentage()
    {
        if ($this->total_unidades <= 0) {
            return 100;
        }
        return round(($this->unidades_listas / $this->total_unidades) * 100);
    }

    /**
     * Unidades faltantes para QC
     */
    public function getUnidadesFaltantes()
    {
        return max(0, $this->total_unidades - $this->unidades_listas);
    }

    /**
     * Inicializar contadores al entrar a Control de Calidad
     */
    public function inicializarQc()
    {
        $total = $this->items()->sum('items_paquetes.quantity');
        $this->update([
            'total_unidades' => $total,
            'unidades_en_taller' => $total,
            'unidades_en_reparacion' => 0,
            'unidades_listas' => 0
        ]);
    }
}
