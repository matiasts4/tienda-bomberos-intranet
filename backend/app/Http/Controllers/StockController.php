<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PteSku;
use App\Models\PteStock;
use App\Models\PteMovimiento;
use Illuminate\Support\Facades\DB; 

class StockController extends Controller
{
    
    public function agregarStock(Request $request)
    {
    
        $request->validate([
            'sku' => 'required|exists:pte_skus,sku', 
            'cantidad' => 'required|integer|min:1',  
            'motivo' => 'required|string', 
            'usuario_id' => 'nullable|integer'
        ]);

        $sku = $request->sku;
        $cantidadEntrante = $request->cantidad;

        
        return DB::transaction(function () use ($sku, $cantidadEntrante, $request) {
            
           
            $stock = PteStock::firstOrCreate(
                ['sku' => $sku],
                ['cantidad' => 0]
            );

            $saldoAnterior = $stock->cantidad;
            
            // update stock
            $stock->cantidad += $cantidadEntrante;
            $stock->save();

            //guardar movimiento
            PteMovimiento::create([
                'sku' => $sku,
                'tipo_movimiento' => $request->motivo, //"INGRESO_BODEGA"
                'cantidad' => $cantidadEntrante,
                'saldo_anterior' => $saldoAnterior,
                'saldo_nuevo' => $stock->cantidad,
                'fecha_hora' => now(),
                'usuario_id' => $request->usuario_id
            ]);

            return response()->json([
                'mensaje' => 'Stock actualizado con éxito',
                'sku' => $sku,
                'nuevo_total' => $stock->cantidad
            ], 200);
        });
    }

    
    public function consultarStock($sku) // consultar stock .
    {
        $stock = PteStock::where('sku', $sku)->first();

        if (!$stock) {
            return response()->json(['sku' => $sku, 'cantidad' => 0], 200);
        }

        return response()->json($stock, 200);
    }
}