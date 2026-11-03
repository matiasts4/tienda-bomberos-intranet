<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class PteMovimiento extends Model
{
    use HasFactory;

    protected $table = 'pte_movimientos';

    protected $fillable = [
        'sku',
        'fecha_hora',
        'tipo_movimiento', // ENTRADA, SALIDA, AJUSTE
        'usuario_id',
        'cotizacion_id',
        'referencia_externa',
        'cliente_nombre',
        'cantidad',
        'saldo_anterior',
        'saldo_nuevo',
        'id_lote', // Nuevo campo agregado
        'peticion_id',
        'observacion',
    ];

    // Relación con el SKU (Producto)
    public function skuProducto()
    {
        return $this->belongsTo(PteSku::class, 'sku', 'sku');
    }
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id');
    }

    public function peticion()
    {
        return $this->belongsTo(Peticion::class, 'peticion_id');
    }

    public function personalizacion()
    {
        return $this->hasOne(Personalizacion::class, 'movimiento_inventario_id');
    }
}
