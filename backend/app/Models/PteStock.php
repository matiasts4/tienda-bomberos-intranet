<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PteStock extends Model
{
    use HasFactory;

    // Mantenemos tu tabla tal cual la tienes
    protected $table = 'pte_stocks';

    // Mantenemos los campos que usa tu StockController + el de alertas
    protected $fillable = ['sku', 'cantidad', 'reserved_stock', 'stock_critico'];

    /**
     * RELACIÓN CORREGIDA:
     * Le cambiamos el nombre de 'sku()' a 'skuProducto()'.
     * 1. Evita conflictos con la columna 'sku'.
     * 2. Coincide con lo que espera el AlertasController.
     */
    public function skuProducto()
    {
        // belongsTo(Modelo, 'foreign_key', 'owner_key')
        return $this->belongsTo(PteSku::class, 'sku', 'sku');
    }

    protected static function booted()
    {
        static::updated(function ($stock) {
            $original = $stock->getOriginal('cantidad');
            $nuevo = $stock->cantidad;
            $critico = $stock->stock_critico;

            // Si estaba bien, y ahora cruzó el umbral crítico (hacia abajo)
            if ($original > $critico && $nuevo <= $critico) {
                
                // Revisar config
                $configActiva = json_decode(\Illuminate\Support\Facades\DB::table('alerta_configs')
                    ->where('clave', 'alerta_inmediata')
                    ->value('valor') ?? 'false');

                if ($configActiva === true) {
                    $emails = json_decode(\Illuminate\Support\Facades\DB::table('alerta_configs')
                        ->where('clave', 'emails')
                        ->value('valor') ?? '[]');

                    if (!empty($emails)) {
                        $producto = $stock->skuProducto;
                        $data = [
                            'tipo' => 'pte',
                            'sku' => $stock->sku,
                            'nombre' => $producto ? $producto->nombre : 'Desconocido',
                            'stock_actual' => $nuevo,
                            'stock_critico' => $critico,
                            'unidad' => 'Unidades'
                        ];

                        try {
                            foreach ($emails as $email) {
                                $destinatario = trim($email);
                                \Illuminate\Support\Facades\Mail::send('emails.alerta_individual', $data, function ($message) use ($destinatario, $data) {
                                    $message->to($destinatario)
                                        ->subject("🚨 ALERTA CRÍTICA: {$data['nombre']} (PTE)");
                                });
                            }
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error("Error enviando alerta inmediata PTE: " . $e->getMessage());
                        }
                    }
                }
            }
        });
    }
}