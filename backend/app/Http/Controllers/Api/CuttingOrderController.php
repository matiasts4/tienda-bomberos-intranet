<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrdenCorte;
use App\Models\ItemOrdenCorte;
use App\Models\SuministroOrdenCorte;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CuttingOrderController extends Controller
{
    /**
     * Display a listing of cutting orders.
     */
    public function index()
    {
        $orders = OrdenCorte::with(['items', 'supplies'])->orderBy('created_at', 'desc')->get();
        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * Store a newly created cutting order in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client' => 'nullable|string|max:255',
            'selected_product' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'estimated_days' => 'nullable|integer',
            'items' => 'required|array|min:1',
            'items.*.product_type' => 'required|string',
            'items.*.size' => 'required|string',
            'items.*.color' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.fabric_type' => 'required|string',
            'supplies' => 'nullable|array',
            'supplies.*.name' => 'required_with:supplies|string',
            'supplies.*.type' => 'required_with:supplies|string',
            'supplies.*.quantity' => 'required_with:supplies|numeric|min:0.01',
            'supplies.*.unit' => 'required_with:supplies|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request) {
                $lastOrder = OrdenCorte::orderBy('id', 'desc')->first();
                $nextCode = $lastOrder ? (intval($lastOrder->code) + 1) : 1000;

                $ordenCorte = OrdenCorte::create([
                    'code' => (string) $nextCode,
                    'client' => $request->client ?? 'Orden de Corte General',
                    'selected_product' => $request->selected_product,
                    'status' => 'order-received',
                    'notes' => $request->notes,
                    'estimated_days' => $request->estimated_days ?? 7,
                    'progress' => 0,
                ]);

                foreach ($request->items as $itemData) {
                    $ordenCorte->items()->create($itemData);
                }

                if ($request->has('supplies')) {
                    foreach ($request->supplies as $supplyData) {
                        $ordenCorte->supplies()->create($supplyData);
                    }
                }

                $ordenCorte->load(['items', 'supplies']);

                return response()->json([
                    'success' => true,
                    'message' => 'Orden de corte creada exitosamente',
                    'data' => $ordenCorte
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear orden de corte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified cutting order.
     */
    public function show($id)
    {
        $ordenCorte = OrdenCorte::with(['items', 'supplies'])->find($id);

        if (!$ordenCorte) {
            return response()->json([
                'success' => false,
                'message' => 'Orden de corte no encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $ordenCorte
        ]);
    }

    /**
     * Update the specified cutting order in storage.
     */
    public function update(Request $request, $id)
    {
        $ordenCorte = OrdenCorte::find($id);

        if (!$ordenCorte) {
            return response()->json([
                'success' => false,
                'message' => 'Orden de corte no encontrada'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|string|in:order-received,in-cutting,in-assembly,in-quality-control,completed,delivered,cutting,cutting-finished,transit,sewing,quality-control,finished',
            'progress' => 'sometimes|numeric|min:0|max:100',
            'notes' => 'sometimes|nullable|string',
            'estimated_days' => 'sometimes|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToUpdate = $request->only(['notes', 'estimated_days', 'progress']);

        if ($request->has('status')) {
            // Mapeo de alias del frontend a estados canónicos del backend
            $statusAliases = [
                'cutting'         => 'in-cutting',
                'cutting-finished'=> 'in-assembly',
                'sewing'          => 'in-assembly',
                'quality-control' => 'in-quality-control',
                'transit'         => 'delivered',
                'finished'        => 'completed',
            ];
            $dataToUpdate['status'] = $statusAliases[$request->status] ?? $request->status;
        }

        $ordenCorte->update($dataToUpdate);

        $ordenCorte->load(['items', 'supplies']);

        return response()->json([
            'success' => true,
            'message' => 'Orden de corte actualizada exitosamente',
            'data' => $ordenCorte
        ]);
    }

    /**
     * Remove the specified cutting order from storage.
     */
    public function destroy($id)
    {
        $ordenCorte = OrdenCorte::find($id);

        if (!$ordenCorte) {
            return response()->json([
                'success' => false,
                'message' => 'Orden de corte no encontrada'
            ], 404);
        }

        $ordenCorte->delete();

        return response()->json([
            'success' => true,
            'message' => 'Orden de corte eliminada exitosamente'
        ]);
    }

    /**
     * Get the next available code for a new cutting order.
     */
    public function generateNextCode()
    {
        $lastOrder = OrdenCorte::orderBy('id', 'desc')->first();
        $nextCode = $lastOrder ? (intval($lastOrder->code) + 1) : 1000;

        return response()->json([
            'success' => true,
            'data' => [
                'next_code' => (string) $nextCode
            ]
        ]);
    }
}
