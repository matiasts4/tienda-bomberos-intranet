<?php

namespace App\Services;

use App\Models\PteMovimiento;
use Illuminate\Support\Collection;

/** Consulta de solo lectura compartida por el historial del SKU y su PDF. */
class SkuHistoryService
{
    public function forSku(string $sku): Collection
    {
        $movimientos = PteMovimiento::where('sku', $sku)
            ->with([
                'skuProducto.talla', 'usuario', 'peticion.user:id,name', 'peticion.estado:id,nombre',
                'cotizacion.cliente', 'cotizacion.cuerpo', 'cotizacion.compania.cuerpo', 'cotizacion.contacto',
                'peticion.cotizacion.cliente', 'peticion.cotizacion.cuerpo',
                'peticion.cotizacion.compania.cuerpo', 'peticion.cotizacion.contacto',
            ])
            ->orderByDesc('fecha_hora')->orderByDesc('id')->get();

        $peticionIds = $movimientos->pluck('peticion_id')->filter()->unique();
        $resoluciones = $peticionIds->isEmpty() ? collect() : PteMovimiento::whereIn('peticion_id', $peticionIds)
            ->whereIn('tipo_movimiento', ['SALIDA_VENTA', 'ANULACION_RESERVA'])
            ->with('usuario:id,name')->orderByDesc('fecha_hora')->orderByDesc('id')->get()->unique('peticion_id')->keyBy('peticion_id');

        return $movimientos->map(function (PteMovimiento $mov) use ($resoluciones) {
            $peticion = $mov->peticion;
            // Una referencia externa en el movimiento nunca se enlaza por coincidencia de números.
            $cotizacionId = $mov->cotizacion_id ?? ($mov->referencia_externa ? null : $peticion?->cotizacion_id);
            $referencia = $mov->referencia_externa ?: $peticion?->referencia_externa;
            $cotizacion = $cotizacionId ? $mov->cotizacion : null;
            if (!$cotizacion && $cotizacionId && $peticion?->cotizacion_id == $cotizacionId) {
                $cotizacion = $peticion->cotizacion;
            }
            $resolucion = $resoluciones->get($mov->peticion_id);
            $estado = $peticion?->estado?->nombre;
            $actor = $mov->usuario?->name ?? ($mov->usuario_id ? 'Usuario ID: '.$mov->usuario_id : 'SISTEMA');

            // Conservar los campos y relaciones del contrato anterior, sin serializar las nuevas relaciones.
            return array_merge($mov->attributesToArray(), [
                'usuario' => $mov->usuario?->toArray(),
                'sku_producto' => $mov->skuProducto?->toArray(),
                'cotizacion_id' => $cotizacionId,
                'referencia_externa' => $referencia,
                'cotizacion_origen' => $cotizacionId ? 'interna' : ($referencia ? 'externa' : null),
                'cotizacion_numero' => $cotizacion?->numero ?? ($cotizacionId ? null : $referencia),
                'cotizacion_nombre' => $cotizacionId ? $cotizacion?->nombre : $peticion?->nombre_cotizacion,
                'cotizacion_disponible' => $cotizacionId ? $cotizacion !== null : false,
                'referencia_inconsistente' => (bool) ($cotizacionId && $referencia)
                    || (bool) ($mov->cotizacion_id && $peticion?->cotizacion_id && $mov->cotizacion_id != $peticion->cotizacion_id)
                    || (bool) ($mov->referencia_externa && $peticion?->referencia_externa && $mov->referencia_externa !== $peticion->referencia_externa),
                'cliente_mostrado' => $mov->cliente_nombre ?: ($cotizacionId ? $cotizacion?->nombre_cliente : $peticion?->cliente_externo),
                'usuario_nombre' => $actor,
                'rol_actor' => match ($mov->tipo_movimiento) {
                    'RESERVA_PRODUCTO' => 'Solicitó',
                    'SALIDA_VENTA' => $mov->peticion_id ? 'Aprobó' : 'Registró',
                    'ANULACION_RESERVA' => 'Rechazó',
                    default => 'Registró',
                },
                'solicitante' => $peticion?->user?->name,
                'estado_solicitud_actual' => $estado ? match ($estado) {
                    'Despachado', 'Aceptado' => 'Aprobada',
                    'Rechazado' => 'Rechazada',
                    default => $estado,
                } : null,
                'resuelto_por' => $resolucion?->usuario?->name,
                'resuelto_en' => $resolucion?->fecha_hora,
                'resolucion_tipo' => $resolucion?->tipo_movimiento,
                'observacion_solicitud' => $peticion?->observacion,
                'variacion_disponible' => (int) $mov->saldo_nuevo - (int) $mov->saldo_anterior,
            ]);
        });
    }
}
