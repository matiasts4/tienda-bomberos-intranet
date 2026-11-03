<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MpLote extends Model
{
    use HasFactory;

    protected $table = 'mp_lotes';

    protected $fillable = [
        'material_id',
        'proveedor_id',         // <--- TRAZABILIDAD
        'codigo_barra_unico',    // R-1001
        'codigo_lote_proveedor', // Tinte Batch A
        'factura_referencia',
        'fecha_ingreso',
        'fecha_vencimiento',
        'cantidad_inicial',
        'cantidad_actual',
        'cantidad_reservada',    // <--- CLAVE PARA FÁBRICA
        'ubicacion',
        'estado' // DISPONIBLE, AGOTADO, CUARENTENA
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'fecha_vencimiento' => 'date',
        'cantidad_actual' => 'decimal:2',
        'cantidad_reservada' => 'decimal:2',
    ];

    // Relación padre
    public function material()
    {
        return $this->belongsTo(MpMaterial::class, 'material_id');
    }

    // Proveedor de origen
    public function proveedor()
    {
        return $this->belongsTo(MpProveedor::class, 'proveedor_id');
    }

    // Historial
    public function movimientos()
    {
        return $this->hasMany(MpMovimiento::class, 'lote_id');
    }

    // --- LÓGICA DE NEGOCIO ---

    // ¿Cuánto puedo usar realmente? (Físico - Reservado)
    public function getDisponibleAttribute()
    {
        return max(0, $this->cantidad_actual - $this->cantidad_reservada);
    }

    protected static function booted()
    {
        static::updated(function ($lote) {
            // Solo evaluar si la cantidad bajó
            $originalCantidad = $lote->getOriginal('cantidad_actual');
            $nuevaCantidad = $lote->cantidad_actual;

            if ($nuevaCantidad < $originalCantidad) {
                $material = $lote->material;
                if (!$material) return;

                $totalActual = $material->stock_total;
                // Cuanto había antes de este descuento:
                $diferencia = $originalCantidad - $nuevaCantidad;
                $totalAnterior = $totalActual + $diferencia;
                $critico = $material->stock_minimo;

                if ($totalAnterior > $critico && $totalActual <= $critico) {
                    // Revisar config
                    $configActiva = json_decode(\Illuminate\Support\Facades\DB::table('alerta_configs')
                        ->where('clave', 'alerta_inmediata')
                        ->value('valor') ?? 'false');

                    if ($configActiva === true) {
                        $emails = json_decode(\Illuminate\Support\Facades\DB::table('alerta_configs')
                            ->where('clave', 'emails')
                            ->value('valor') ?? '[]');

                        if (!empty($emails)) {
                            $data = [
                                'tipo' => 'mp',
                                'sku' => $material->codigo_interno,
                                'nombre' => $material->nombre_base,
                                'stock_actual' => $totalActual,
                                'stock_critico' => $critico,
                                'unidad' => $material->unidad ? $material->unidad->abreviacion : 'Unidades'
                            ];

                            try {
                                foreach ($emails as $email) {
                                    $destinatario = trim($email);
                                    \Illuminate\Support\Facades\Mail::send('emails.alerta_individual', $data, function ($message) use ($destinatario, $data) {
                                        $message->to($destinatario)
                                            ->subject("🚨 ALERTA CRÍTICA: {$data['nombre']} (MP)");
                                    });
                                }
                            } catch (\Exception $e) {
                                \Illuminate\Support\Facades\Log::error("Error enviando alerta inmediata MP: " . $e->getMessage());
                            }
                        }
                    }
                }
            }
        });
    }
}
?>