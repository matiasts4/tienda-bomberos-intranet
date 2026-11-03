<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use App\Models\Despacho;
use App\Models\Estado;
use App\Models\HistorialEtapa;
use Illuminate\Http\Request;

class DespachoController extends Controller
{
    public function getKanbanData()
    {
        $estadosVentas = Estado::where('scope', 'cotizacion')->whereIn('nombre', ['Ventas', 'Aprobada por Cliente'])->pluck('id');

        if ($estadosVentas->isEmpty()) {
            return response()->json(['error' => 'No se encontró el estado Ventas en cotizaciones'], 404);
        }

        // Get states for despacho
        $estadosDespacho = Estado::where('scope', 'despacho')->orderBy('orden')->get();
        $estadoInicialDespacho = $estadosDespacho->first();

        // Get cotizaciones in Ventas
        $cotizaciones = Cotizacion::with([
            'cliente', 
            'cuerpo', 'compania.cuerpo', 'contacto', 'estado',
            'personalizaciones:id,cotizacion_id,numero_item,cantidad,estado,siguiente_proceso,bloqueado_qc,configuracion',
            'ejecutivo', 
            'despachoModelo.estado',
            'oportunidad.gestiones',
            'gestiones',
            'archivos',
            'detalles.skuGenerico'
        ])
        ->whereIn('estado_id', $estadosVentas)
        ->get();

        \App\Services\QuotePersonalization::primeGenerics($cotizaciones->flatMap->detalles);

        // Map data to ensure consistency for frontend
        $data = $cotizaciones->map(function ($cot) use ($estadoInicialDespacho) {
            $despacho = $cot->despachoModelo;
            
            // If it doesn't have a despacho record, we virtually assign it to the first dispatch state
            $estadoActual = $despacho && $despacho->estado ? $despacho->estado : $estadoInicialDespacho;

            return [
                'id' => $cot->id, // using cotizacion id as the main draggable ID
                'cotizacion' => $cot,
                'despacho' => $despacho,
                'estado_despacho' => $estadoActual,
                'cliente' => $cot->cliente,
                'ejecutivo' => $cot->ejecutivo,
                'bloqueo_despacho' => \App\Services\PersonalizationWorkflow::dispatchBlocker($cot),
                'componentes_kit' => $cot->detalles->flatMap(fn ($item) => \App\Services\KitPersonalization::manifest($item))->values(),
            ];
        });

        return response()->json([
            'estados' => $estadosDespacho,
            'items' => $data
        ]);
    }

    public function updateEstado(Request $request, $cotizacion_id)
    {
        \App\Services\PersonalizationWorkflow::authorize('Despacho');
        $request->validate([
            'estado_id' => ['required', \Illuminate\Validation\Rule::exists('estados', 'id')->where('scope', 'despacho')],
            'calle' => 'nullable|string|max:150', 'numero_calle' => 'nullable|string|max:20',
            'comuna' => 'nullable|string|max:100', 'ciudad' => 'nullable|string|max:100',
            'agencia' => 'nullable|string|max:100', 'fecha_despacho' => 'nullable|date', 'observacion' => 'nullable|string',
            'voucher_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'voucher' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);
        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $cotizacion_id) {
            $quote = Cotizacion::lockForUpdate()->findOrFail($cotizacion_id);
            $blocker = \App\Services\PersonalizationWorkflow::dispatchBlocker($quote);
            abort_if($blocker !== null, 422, $blocker ?? '');
            $next = Estado::where('scope', 'despacho')->findOrFail($request->estado_id);
            $dispatch = \App\Services\PersonalizationWorkflow::ensureDispatch($quote);
            $previous = $dispatch->estado_id;
            // Two historical seeders used different names/orders; keep both paths
            // without forcing delivered orders through cancellation or back to preparation.
            $edges = [
                'Por despachar' => ['Preparando despacho', 'Listo para despacho'],
                'Preparando despacho' => ['Saliendo de bodega'],
                'Saliendo de bodega' => ['Entregado a agencia'],
                'Listo para despacho' => ['En tránsito'],
                'En tránsito' => ['Entregado'],
            ];
            abort_if($previous != $next->id && !in_array($next->nombre, $edges[$dispatch->estado?->nombre] ?? [], true),
                422, 'Avanza a la siguiente etapa de Despacho; no se pueden saltar etapas ni retroceder.');
            $file = $request->file('voucher_file') ?? $request->file('voucher');
            abort_if($next->nombre === 'Entregado a agencia' && !$file && !$dispatch->voucher_url,
                422, 'Adjunta el voucher antes de confirmar la entrega a la agencia.');
            $data = $request->only(['calle', 'numero_calle', 'comuna', 'ciudad', 'agencia', 'fecha_despacho', 'observacion']);
            $data['estado_id'] = $next->id;
            if ($file) $data['voucher_url'] = '/storage/' . $file->store('vouchers_agencia', 'public');
            $dispatch->update($data);
            if ($previous != $next->id) {
                HistorialEtapa::create(['model_type' => Despacho::class, 'model_id' => $dispatch->id,
                    'user_id' => auth()->id(), 'estado_anterior_id' => $previous, 'estado_nuevo_id' => $next->id]);
            }
            return response()->json(['message' => 'Estado de despacho actualizado', 'despacho' => $dispatch->load('estado')]);
        });
    }

    public function downloadGuiaPdf($cotizacion_id)
    {
        $cotizacion = Cotizacion::with(['cliente', 'contacto', 'cuerpo', 'compania.cuerpo', 'detalles', 'despachoModelo', 'oportunidad.contacto'])->findOrFail($cotizacion_id);

        if (!$cotizacion->despachoModelo) {
            return response()->json(['error' => 'No hay despacho asociado para imprimir la guía'], 404);
        }

        $despacho = $cotizacion->despachoModelo;

        // Ancla directa a Cuerpo/Compañía (esquema nuevo, sin cliente espejo)
        $cuerpo = $cotizacion->cuerpo ?? $cotizacion->compania?->cuerpo;

        if ($cuerpo) {
            $nombreClientePdf = 'Cuerpo de Bomberos de ' . $cuerpo->nombre;
            $rutClientePdf = $cuerpo->rut ?? 'Sin RUT';
        } elseif ($cotizacion->cliente) {
            $targetCliente = $cotizacion->cliente;

            $nombreClientePdf = $targetCliente->nombre_empresa ?? $targetCliente->nombre_contacto ?? 'Sin Nombre';
            $rutClientePdf = $targetCliente->rut_empresa ?? $targetCliente->rut ?? 'Sin RUT';

            if (str_starts_with($nombreClientePdf, 'CB ')) {
                $parte = substr($nombreClientePdf, 3);
                $nombreClientePdf = "Cuerpo de Bomberos de " . $parte;
            }
        } else {
            $nombreClientePdf = $cotizacion->contacto->nombre ?? 'Sin Nombre';
            $rutClientePdf = $cotizacion->contacto->rut ?? 'Sin RUT';
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.guia_despacho', [
            'cotizacion' => $cotizacion,
            'despacho' => $despacho,
            'nombreClientePdf' => $nombreClientePdf,
            'rutClientePdf' => $rutClientePdf,
        ]);

        $nombreArchivo = "guia_despacho_{$cotizacion->id}.pdf";
        return $pdf->stream($nombreArchivo);
    }
}
