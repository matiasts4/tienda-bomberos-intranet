<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MpMovimiento extends Model
{
    use HasFactory;

    protected $table = 'mp_movimientos';

    protected $fillable = [
        'lote_id',
        'proveedor_id',    // <--- TRAZABILIDAD
        'tipo_movimiento', // INGRESO, SALIDA_CORTE, REPARACION
        'cantidad',        // + o -
        'usuario_id',
        'documento_respaldo',
        'observacion'
    ];

    public function lote()
    {
        return $this->belongsTo(MpLote::class, 'lote_id');
    }

    public function proveedor()
    {
        return $this->belongsTo(MpProveedor::class, 'proveedor_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
} ?>