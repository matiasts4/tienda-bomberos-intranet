<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\OrdenCorte as CuttingOrder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;

class PackageController extends Controller
{
    /**
     * Obtener todos los paquetes
     */
    public function index(): JsonResponse
    {
        $packages = Package::with(['items.cuttingOrder'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($packages);
    }

    /**
     * Crear un nuevo paquete
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'destination' => 'required|string|max:255',
                'transport_type' => 'required|string|max:255',
                'priority' => 'required|in:low,medium,high',
                'notes' => 'nullable|string',
                'estimated_delivery_days' => 'required|integer|min:1|max:30',
                'items' => 'required|array|min:1',
                'items.*.id' => 'required|integer|exists:items_ordenes_corte,id', // CuttingOrderItem ID
                'items.*.quantity' => 'required|integer|min:1'
            ]);

            // Generar c├│digo autom├ítico
            $code = Package::generateNextCode();

            // Crear el paquete
            $package = Package::create([
                'code' => $code,
                'destination' => $validatedData['destination'],
                'transport_type' => $validatedData['transport_type'],
                'priority' => $validatedData['priority'],
                'notes' => $validatedData['notes'] ?? '',
                'estimated_delivery_days' => $validatedData['estimated_delivery_days'],
                'status' => 'transit'
            ]);

            // Preparar datos para sync/attach
            $itemsData = [];
            $cuttingOrderIds = [];
            
            foreach ($validatedData['items'] as $itemData) {
                // Obtener el item para saber a qu├® orden pertenece
                $orderItem = \App\Models\ItemOrdenCorte::find($itemData['id']);
                if ($orderItem) {
                    $cuttingOrderIds[] = $orderItem->cutting_order_id;
                    $itemsData[$itemData['id']] = [
                        'quantity' => $itemData['quantity']
                    ];
                }
            }

            // Asociar los items al paquete
            $package->items()->attach($itemsData);
            
            // L├│gica Legacy: Mantener sincronizada la tabla pivot antigua packages_cutting_orders por ahora
            // para no romper vistas que dependan de ella, aunque sea solo un resumen.
            // Agrupamos por orden de corte.
            // Para product y color, tomamos el del primer item de esa orden que encontremos (es una aproximaci├│n)
            $uniqueOrderIds = array_unique($cuttingOrderIds);

            // VALIDACI├ôN: Asegurar que todos los ├¡tems pertenezcan a la MISMA orden de corte
            if (count($uniqueOrderIds) > 1) {
                return response()->json([
                    'message' => 'Error de validaci├│n',
                    'errors' => ['items' => ['Todos los ├¡tems del paquete deben pertenecer a la misma Orden de Corte.']]
                ], 422);
            }
            
            // Verificar estado de cada orden: ┬┐Est├ín todos sus items completamente empaquetados?
            foreach ($uniqueOrderIds as $orderId) {
                $order = CuttingOrder::with(['items' => function($q) {
                    $q->withSum('packages as packaged_quantity', 'items_paquetes.quantity');
                }])->find($orderId);

                if ($order) {
                    $allFullyPackaged = true;
                    foreach ($order->items as $item) {
                       // La suma 'packaged_quantity' incluye lo que acabamos de agregar porque attach() ya sucedi├│
                       $packaged = (int)($item->packaged_quantity ?? 0);
                       if ($packaged < (int)$item->quantity) {
                           $allFullyPackaged = false;
                           break;
                       }
                    }

                    // Si todos los items est├ín totalmente empaquetados, avanzamos la orden.
                    // Si no, la dejamos en 'cutting-finished' (u otro estado que indique parcialidad)
                    \Illuminate\Support\Facades\Log::info("Checking Order {$order->id} completion status:", [
                        'items' => $order->items->map(function($i) {
                            return ['id' => $i->id, 'total' => $i->quantity, 'packaged' => $i->packaged_quantity];
                        })
                    ]);

                    if ($allFullyPackaged) {
                        \Illuminate\Support\Facades\Log::info("Order {$order->id} is fully packaged. Updating status to pending-warehouse.");
                        $order->update(['status' => 'pending-warehouse']);
                        \Illuminate\Support\Facades\Log::info("Order {$order->id} status updated.");
                    } else {
                        // Aseguramos que est├® en cutting-finished para que siga apareciendo en la lista
                        if ($order->status !== 'cutting-finished') {
                            $order->update(['status' => 'cutting-finished']);
                        }
                    }
                }
            }

            // LOGGING
            \App\Models\FactoryLog::create([
                'action' => 'Paquete Creado',
                'description' => "Se cre├│ el paquete {$package->code} con destino {$package->destination}",
                'details' => json_encode($package->toArray()),
                'entity_type' => 'Package',
                'entity_id' => $package->id,
                'user_id' => $request->user()?->id
            ]);

            // Cargar las relaciones para la respuesta
            $package->load(['items.cuttingOrder']);

            return response()->json([
                'message' => 'Paquete creado exitosamente',
                'package' => $package
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Datos de validaci├│n incorrectos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el paquete',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener el siguiente c├│digo de paquete
     */
    public function getNextCode(): JsonResponse
    {
        return response()->json([
            'code' => Package::generateNextCode()
        ]);
    }

    /**
     * Actualizar estado de un paquete
     */
    public function updateStatus(Request $request, Package $package): JsonResponse
    {
        $validatedData = $request->validate([
            'status' => 'required|in:preparing,transit,delivered,returned,sewing,quality-control,finished',
            'workshop' => 'nullable|string',
            'workshop_notes' => 'nullable|string'
        ]);

        $oldStatus = $package->status;
        $newStatus = $validatedData['status'];

        // =====================================================
        // VALIDACIONES DE CONTROL DE CALIDAD
        // =====================================================

        // Validaci├│n 1: No se puede salir de 'quality-control' sin completar QC
        if ($oldStatus === 'quality-control' && $newStatus !== 'quality-control') {
            $validationError = $this->validateQualityControlTransition($package, $newStatus);
            if ($validationError) {
                return response()->json($validationError, 422);
            }
        }

        // Validaci├│n 2: No se puede mover a 'finished' sin completar QC
        if ($newStatus === 'finished') {
            $validationError = $this->validateFinishedTransition($package);
            if ($validationError) {
                return response()->json($validationError, 422);
            }
        }

        // Si el paquete entra a quality-control, inicializar los contadores de QC
        if ($newStatus === 'quality-control' && $oldStatus !== 'quality-control') {
            $package->inicializarQc();
        }

        $package->update($validatedData);

        // LOGGING
        $statusMap = [
            'preparing' => 'Preparaci├│n',
            'cutting-finished' => 'Corte Terminado',
            'transit' => 'Tr├ínsito',
            'sewing' => 'Confecci├│n',
            'quality-control' => 'Control de Calidad',
            'finished' => 'Terminado',
            'delivered' => 'Entregado',
            'returned' => 'Devuelto'
        ];

        $statusLabel = $statusMap[$package->status] ?? $package->status;
        $description = "El paquete {$package->code} cambi├│ de estado a {$statusLabel}";
        
        if (isset($validatedData['workshop'])) {
            $description .= " y fue asignado al taller {$validatedData['workshop']}";
        }

        \App\Models\FactoryLog::create([
            'action' => 'Actualizaci├│n de Paquete',
            'description' => $description,
            'details' => ['old_status' => $oldStatus, 'new_status' => $package->status, 'workshop' => $package->workshop],
            'entity_type' => 'Package',
            'entity_id' => $package->id,
            'user_id' => $request->user()?->id
        ]);

        return response()->json([
            'message' => 'Estado del paquete actualizado',
            'package' => $package
        ]);
    }
    /**
     * Registrar paso a Control de Calidad con posible Reparaci├│n
     */
    public function registerQualityControl(Request $request, Package $package): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'waste_items' => 'array', // Items que son merma: [{ id: cutting_order_item_id, quantity: 2 }]
                'waste_items.*.id' => 'required|integer',
                'waste_items.*.quantity' => 'required|integer|min:1',
                'notes' => 'nullable|string'
            ]);

            $wasteItemsInput = $validatedData['waste_items'] ?? [];
            $notes = $validatedData['notes'] ?? '';

            \Log::info('registerQualityControl - Input recibido', [
                'package_id' => $package->id,
                'package_code' => $package->code,
                'is_repair_package' => $package->is_repair_package,
                'parent_package_id' => $package->parent_package_id,
                'waste_items' => $wasteItemsInput,
                'notes' => $notes
            ]);

            \Illuminate\Support\Facades\DB::beginTransaction();

            $wastePackage = null;

            // 1. Si hay merma/reparaci├│n, crear paquete de reparaci├│n
            if (!empty($wasteItemsInput)) {
                // Generar c├│digo para el paquete de reparacion
                $wasteCode = Package::generateNextCode();
                
                // Determinar el paquete padre real (si este es un paquete de reparaci├│n, usar su padre)
                $realParentId = $package->is_repair_package ? $package->parent_package_id : $package->id;
                
                \Log::info('registerQualityControl - Creando paquete de reparaci├│n', [
                    'wasteCode' => $wasteCode,
                    'original_package' => $package->code,
                    'real_parent_id' => $realParentId
                ]);

                // Crear el paquete de reparacion con referencia al padre
                $wastePackage = Package::create([
                    'code' => $wasteCode,
                    'destination' => "Reparaci├│n - " . str_replace('Reparaci├│n - ', '', $package->destination),
                    'transport_type' => $package->transport_type,
                    'priority' => 'high',
                    'notes' => "Reparaci├│n generada del paquete {$package->code}. " . $notes,
                    'estimated_delivery_days' => $package->estimated_delivery_days,
                    'status' => 'sewing', // Mover a Confecci├│n directamente
                    'workshop' => $package->workshop,
                    'parent_package_id' => $realParentId, // Referencia al paquete padre
                    'is_repair_package' => true,
                ]);

                // Mover items al paquete de reparaci├│n
                foreach ($wasteItemsInput as $wasteItem) {
                    $itemId = $wasteItem['id'];
                    $wasteQty = $wasteItem['quantity'];

                    $currentPivot = $package->items()->where('cutting_order_item_id', $itemId)->first();

                    if (!$currentPivot) {
                        throw new \Exception("El item {$itemId} no pertenece al paquete.");
                    }

                    $currentQty = $currentPivot->pivot->quantity;

                    if ($wasteQty > $currentQty) {
                         throw new \Exception("La cantidad de reparaci├│n ({$wasteQty}) excede la cantidad en el paquete ({$currentQty}) para el item {$itemId}.");
                    }

                    // A. Attach al paquete de Reparaci├│n
                    $wastePackage->items()->attach($itemId, ['quantity' => $wasteQty]);

                    // B. Actualizar o Detach del paquete actual
                    if ($wasteQty == $currentQty) {
                        $package->items()->detach($itemId);
                    } else {
                        $package->items()->updateExistingPivot($itemId, ['quantity' => $currentQty - $wasteQty]);
                    }
                }

                // Actualizar contador de unidades en reparaci├│n del paquete padre
                $totalWasteUnits = array_sum(array_column($wasteItemsInput, 'quantity'));
                if ($realParentId && $realParentId !== $package->id) {
                    $parentPackage = Package::find($realParentId);
                    if ($parentPackage) {
                        $parentPackage->increment('unidades_en_reparacion', $totalWasteUnits);
                    }
                } else {
                    $package->increment('unidades_en_reparacion', $totalWasteUnits);
                }

                \App\Models\FactoryLog::create([
                    'action' => 'Reparaci├│n Registrada',
                    'description' => "Se gener├│ el paquete de reparaci├│n {$wastePackage->code} a partir de {$package->code}",
                    'details' => json_encode(['waste_items' => $wasteItemsInput, 'parent_id' => $realParentId]),
                    'entity_type' => 'Package',
                    'entity_id' => $wastePackage->id,
                    'user_id' => $request->user()?->id
                ]);
            }

            // 2. Determinar qu├® hacer con el paquete actual
            $hasWasteItems = !empty($wasteItemsInput);
            $isRepairPackage = $package->is_repair_package;
            $hasItemsLeft = $package->items()->count() > 0;

            if ($isRepairPackage) {
                // CASO: Es un paquete de REPARACI├ôN completando QC
                $parentPackage = Package::find($package->parent_package_id);

                if ($hasWasteItems) {
                    // Tiene m├ís merma, se queda en QC esperando (o se elimina si qued├│ vac├¡o)
                    if (!$hasItemsLeft) {
                        $package->update([
                            'status' => 'returned',
                            'notes' => ($package->notes ?? '') . " (Todo pas├│ a nueva reparaci├│n)"
                        ]);
                    }
                    // Si tiene items, se queda en quality-control
                } else {
                    // Todo OK - Reintegrar items al paquete padre
                    if ($parentPackage && $hasItemsLeft) {
                        foreach ($package->items as $item) {
                            $existingPivot = $parentPackage->items()
                                ->where('cutting_order_item_id', $item->id)
                                ->first();

                            if ($existingPivot) {
                                // Sumar al existente
                                $parentPackage->items()->updateExistingPivot(
                                    $item->id,
                                    ['quantity' => $existingPivot->pivot->quantity + $item->pivot->quantity]
                                );
                            } else {
                                // Agregar nuevo
                                $parentPackage->items()->attach($item->id, ['quantity' => $item->pivot->quantity]);
                            }
                        }

                        // Actualizar contadores del padre
                        $repairedUnits = $package->items->sum('pivot.quantity');
                        $parentPackage->decrement('unidades_en_reparacion', $repairedUnits);
                        $parentPackage->increment('unidades_listas', $repairedUnits);

                        \App\Models\FactoryLog::create([
                            'action' => 'Reparaci├│n Completada',
                            'description' => "Items del paquete {$package->code} reintegrados a {$parentPackage->code}",
                            'details' => json_encode(['repaired_units' => $repairedUnits]),
                            'entity_type' => 'Package',
                            'entity_id' => $parentPackage->id,
                            'user_id' => $request->user()?->id
                        ]);

                        // Verificar si el padre puede pasar a finished
                        if ($parentPackage->unidades_en_reparacion <= 0) {
                            $parentPackage->update(['status' => 'finished']);
                            
                            \App\Models\FactoryLog::create([
                                'action' => 'Control de Calidad Finalizado',
                                'description' => "El paquete {$parentPackage->code} complet├│ todas sus reparaciones y pas├│ a Terminado",
                                'entity_type' => 'Package',
                                'entity_id' => $parentPackage->id,
                                'user_id' => $request->user()?->id
                            ]);
                        }
                    }

                    // El paquete de reparaci├│n pasa a finished
                    $package->update(['status' => 'finished']);
                }
            } else {
                // CASO: Es un paquete NORMAL completando QC
                if ($hasWasteItems) {
                    // Hay merma - SE QUEDA en quality-control esperando reparaciones
                    // No cambiar estado, solo actualizar contadores
                    $goodUnits = $package->items->sum('pivot.quantity');
                    $package->update(['unidades_listas' => $goodUnits]);
                    
                    \App\Models\FactoryLog::create([
                        'action' => 'QC Parcial - Esperando Reparaci├│n',
                        'description' => "El paquete {$package->code} tiene {$goodUnits} unidades listas, esperando reparaci├│n",
                        'details' => json_encode(['unidades_listas' => $goodUnits, 'en_reparacion' => $package->unidades_en_reparacion]),
                        'entity_type' => 'Package',
                        'entity_id' => $package->id,
                        'user_id' => $request->user()?->id
                    ]);
                } else {
                    // Todo OK - Pasar a finished
                    if ($hasItemsLeft) {
                        $package->update(['status' => 'finished']);
                        
                        \App\Models\FactoryLog::create([
                            'action' => 'Control de Calidad Finalizado',
                            'description' => "El paquete {$package->code} complet├│ Control de Calidad y pas├│ a Terminado",
                            'entity_type' => 'Package',
                            'entity_id' => $package->id,
                            'user_id' => $request->user()?->id
                        ]);
                    } else {
                        $package->update([
                            'status' => 'returned',
                            'notes' => ($package->notes ?? '') . " (Sin items)"
                        ]);
                    }
                }
            }

            \Illuminate\Support\Facades\DB::commit();

            return response()->json([
                'message' => 'Proceso de Control de Calidad registrado',
                'package' => $package->fresh(['items', 'parentPackage', 'repairPackages']),
                'waste_package' => $wastePackage ? $wastePackage->fresh(['items']) : null
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            \Log::error('registerQualityControl - Error', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Error al registrar Control de Calidad',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar detalle de paquete a PDF
     */
    public function exportPdf(Package $package)
    {
        // Cargar relaciones necesarias
        $package->load(['items.cuttingOrder']);
        
        // Generar PDF usando la vista
        $pdf = Pdf::loadView('pdf.package', [
            'package' => $package
        ]);

        // Configurar papel
        $pdf->setPaper('a4', 'portrait');

        $nombreArchivo = "paquete_{$package->code}.pdf";

        // Devolver stream para ver en el navegador (o download para forzar descarga)
        return $pdf->stream($nombreArchivo);
    }

    // ==========================================
    // M├ëTODOS DE CONTROL DE CALIDAD (QC)
    // ==========================================

    /**
     * Actualizar progreso parcial de Control de Calidad
     */
    public function updatePartialQc(Request $request, Package $package): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'unidades_listas' => 'required|integer|min:0',
                'unidades_en_reparacion' => 'sometimes|integer|min:0',
                'unidades_en_taller' => 'sometimes|integer|min:0',
            ]);

            $oldListas = $package->unidades_listas;
            $oldReparacion = $package->unidades_en_reparacion;

            // Actualizar los valores
            $package->update([
                'unidades_listas' => $validatedData['unidades_listas'],
                'unidades_en_reparacion' => $validatedData['unidades_en_reparacion'] ?? $package->unidades_en_reparacion,
                'unidades_en_taller' => $validatedData['unidades_en_taller'] ?? $package->unidades_en_taller,
            ]);

            // LOGGING
            \App\Models\FactoryLog::create([
                'action' => 'QC Parcial Actualizado',
                'description' => "Paquete {$package->code}: {$oldListas}ÔåÆ{$package->unidades_listas} listas, {$oldReparacion}ÔåÆ{$package->unidades_en_reparacion} en reparaci├│n",
                'details' => json_encode([
                    'old' => ['listas' => $oldListas, 'reparacion' => $oldReparacion],
                    'new' => ['listas' => $package->unidades_listas, 'reparacion' => $package->unidades_en_reparacion],
                    'total' => $package->total_unidades,
                    'progress' => $package->getQcProgressPercentage() . '%'
                ]),
                'entity_type' => 'Package',
                'entity_id' => $package->id,
                'user_id' => $request->user()?->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Progreso de QC actualizado exitosamente',
                'data' => $package->fresh()
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validaci├│n',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar QC',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Valida si un paquete puede salir del estado 'Control de Calidad'
     */
    private function validateQualityControlTransition(Package $package, string $newStatus): ?array
    {
        // Si total_unidades es 0, permitir (no hay QC que validar)
        if ($package->total_unidades <= 0) {
            return null;
        }

        // Verificar todas las condiciones
        $unidadesCompletas = $package->unidades_listas >= $package->total_unidades;
        $hayReparaciones = $package->unidades_en_reparacion > 0;
        $hayEnTaller = $package->unidades_en_taller > 0;

        if (!$unidadesCompletas || $hayReparaciones || $hayEnTaller) {
            $faltantes = $package->getUnidadesFaltantes();
            $porcentaje = $package->getQcProgressPercentage();

            $errors = [
                "El paquete debe permanecer en Control de Calidad hasta completar todas las unidades."
            ];

            if (!$unidadesCompletas) {
                $errors[] = "Faltan {$faltantes} unidades por procesar ({$porcentaje}% completado).";
            }

            if ($hayReparaciones) {
                $errors[] = "Hay {$package->unidades_en_reparacion} unidades en reparaci├│n que deben ser procesadas.";
            }

            if ($hayEnTaller) {
                $errors[] = "Hay {$package->unidades_en_taller} unidades a├║n en taller.";
            }

            $errors[] = "Progreso actual: {$package->unidades_listas} listas / {$package->unidades_en_reparacion} en reparaci├│n / {$package->unidades_en_taller} en taller (Total: {$package->total_unidades})";
            $errors[] = "Las unidades deben ser registradas mediante entregas parciales del taller.";

            return [
                'success' => false,
                'message' => 'Control de Calidad Incompleto',
                'errors' => ['status' => $errors]
            ];
        }

        return null; // Validaci├│n exitosa
    }

    /**
     * Valida si un paquete puede moverse al estado 'Terminado'
     */
    private function validateFinishedTransition(Package $package): ?array
    {
        // Si total_unidades es 0, permitir
        if ($package->total_unidades <= 0) {
            return null;
        }

        // Verificar todas las condiciones
        $unidadesCompletas = $package->unidades_listas >= $package->total_unidades;
        $hayReparaciones = $package->unidades_en_reparacion > 0;
        $hayEnTaller = $package->unidades_en_taller > 0;

        if (!$unidadesCompletas || $hayReparaciones || $hayEnTaller) {
            $faltantes = $package->getUnidadesFaltantes();
            $porcentaje = $package->getQcProgressPercentage();

            $errors = [
                "No se puede mover el paquete a Terminado. El Control de Calidad no est├í completo."
            ];

            if (!$unidadesCompletas) {
                $errors[] = "Faltan {$faltantes} unidades por procesar ({$porcentaje}% completado).";
            }

            if ($hayReparaciones) {
                $errors[] = "Hay {$package->unidades_en_reparacion} unidades en reparaci├│n.";
            }

            if ($hayEnTaller) {
                $errors[] = "Hay {$package->unidades_en_taller} unidades en taller.";
            }

            $errors[] = "Progreso: {$package->unidades_listas} / {$package->total_unidades} unidades listas.";

            return [
                'success' => false,
                'message' => 'Operaci├│n Denegada',
                'errors' => ['status' => $errors]
            ];
        }

        return null; // Validaci├│n exitosa
    }
}
