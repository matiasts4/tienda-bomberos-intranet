<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\PteStock;
use App\Models\MpMaterial;
use App\Models\PteMovimiento;
use Carbon\Carbon;

class AlertasController extends Controller
{
    // ==========================================
    // 1. CONFIGURACIÓN
    // ==========================================

    public function getConfig()
    {
        $configs = Schema::hasTable('alerta_configs')
            ? DB::table('alerta_configs')->pluck('valor', 'clave')
            : collect();

        return response()->json([
            'horario' => json_decode($configs['horario'] ?? '"08:30"'),
            'dias' => json_decode($configs['dias'] ?? '[]'),
            'emails' => json_decode($configs['emails'] ?? '[]'),
            'activar_prediccion' => json_decode($configs['activar_prediccion'] ?? 'true'),
            'alerta_inmediata' => json_decode($configs['alerta_inmediata'] ?? 'false'),
            'alerta_vencimientos' => json_decode($configs['alerta_vencimientos'] ?? 'false')
        ]);
    }

    public function updateConfig(Request $request)
    {
        $data = $request->validate([
            'horario' => 'required|string',
            'dias' => 'required|array',
            'emails' => 'required|array',
            'activar_prediccion' => 'boolean',
            'alerta_inmediata' => 'boolean',
            'alerta_vencimientos' => 'boolean'
        ]);

        if (Schema::hasTable('alerta_configs')) {
            foreach ($data as $key => $value) {
                DB::table('alerta_configs')->updateOrInsert(
                    ['clave' => $key],
                    ['valor' => json_encode($value), 'updated_at' => now()]
                );
            }
        }

        return response()->json(['message' => 'Configuración guardada correctamente']);
    }

    // ==========================================
    // 2. DATOS + PREDICCIÓN + KPIS
    // ==========================================

    public function getCriticos(Request $request)
    {
        $perPage = (int) $request->input('per_page', 20);
        $usarPrediccion = Schema::hasTable('alerta_configs')
            ? json_decode(DB::table('alerta_configs')->where('clave', 'activar_prediccion')->value('valor') ?? 'true')
            : true;
        $diasAnalisis = 30;
        $fechaInicio = Carbon::now()->subDays($diasAnalisis);

        // --- 1. PTE: FILTRAR ESTRICTAMENTE PRODUCTOS ACTIVOS Y PUBLICADOS ---
        $basePte = PteStock::query()
            ->whereColumn('cantidad', '<=', 'stock_critico')
            ->whereHas('skuProducto', function ($q) {
                $q->where('activo', true)
                  ->whereHas('skuGenerico', function ($g) {
                      $g->where('estado_catalogo', 'publicado');
                  });
            });

        // Agregación consolidada de consumos últimos 30 días (evita N+1)
        $consumos = PteMovimiento::query()
            ->where('fecha_hora', '>=', $fechaInicio)
            ->whereIn('tipo_movimiento', ['AJUSTE_MANUAL', 'SALIDA_VENTA', 'AJUSTE_INVENTARIO'])
            ->whereColumn('saldo_anterior', '>', 'saldo_nuevo')
            ->groupBy('sku')
            ->selectRaw('sku, ABS(SUM(cantidad)) as total_consumo')
            ->pluck('total_consumo', 'sku');

        $allPteItems = (clone $basePte)
            ->with(['skuProducto.skuGenerico.subfamilia', 'skuProducto.talla'])
            ->get();

        $totalPte = $allPteItems->count();
        $agotadosPte = 0;
        $riesgoInminentePte = 0;
        $stockBajoPte = 0;
        $estancadosPte = 0;
        $deficitUnidadesPte = 0;

        $mappedPte = $allPteItems->map(function ($stock) use ($consumos, $diasAnalisis, $usarPrediccion, &$agotadosPte, &$riesgoInminentePte, &$stockBajoPte, &$estancadosPte, &$deficitUnidadesPte) {
            $prod = $stock->skuProducto;
            $diasRestantes = 'N/A';
            $diasNum = 9999;
            $tendencia = 'normal';
            $estadoSeveridad = 'bajo';
            $deficit = max(0, (int) $stock->stock_critico - (int) $stock->cantidad);
            $deficitUnidadesPte += $deficit;

            $consumoTotal = (int) ($consumos[$stock->sku] ?? 0);
            $consumoDiario = $consumoTotal > 0 ? round($consumoTotal / $diasAnalisis, 2) : 0;

            if ($stock->cantidad == 0) {
                $diasRestantes = "Agotado";
                $diasNum = 0;
                $tendencia = 'critico';
                $estadoSeveridad = 'agotado';
                $agotadosPte++;
            } elseif ($usarPrediccion && $stock->cantidad > 0) {
                if ($consumoTotal > 0 && $consumoDiario > 0) {
                    $dias = $stock->cantidad / $consumoDiario;
                    $diasNum = round($dias, 1);
                    if ($dias < 1) {
                        $diasRestantes = "Menos de 1 día";
                        $tendencia = 'critico';
                        $estadoSeveridad = 'critico';
                        $riesgoInminentePte++;
                    } else {
                        $diasRedondeado = round($dias, 0);
                        $diasRestantes = "{$diasRedondeado} días prox para quiebre";
                        if ($dias < 7) {
                            $tendencia = 'critico';
                            $estadoSeveridad = 'critico';
                            $riesgoInminentePte++;
                        } else {
                            $tendencia = 'normal';
                            $estadoSeveridad = 'bajo';
                            $stockBajoPte++;
                        }
                    }
                } else {
                    $diasRestantes = "Estancado (Sin salidas 30d)";
                    $diasNum = 9999;
                    $tendencia = 'normal';
                    $estadoSeveridad = 'estancado';
                    $estancadosPte++;
                }
            } else {
                $estadoSeveridad = 'bajo';
                $stockBajoPte++;
            }

            return [
                'sku' => $stock->sku,
                'nombre' => $prod ? $prod->nombre : 'Desconocido',
                'talla' => $prod && $prod->talla ? $prod->talla->nombre : 'N/A',
                'detalle' => $prod?->skuGenerico?->subfamilia?->nombre ?? 'Sin familia',
                'stock_actual' => (int) $stock->cantidad,
                'stock_critico' => (int) $stock->stock_critico,
                'deficit' => $deficit,
                'consumo_30d' => $consumoTotal,
                'consumo_diario' => $consumoDiario,
                'dias_para_quiebre' => $diasRestantes,
                'dias_num' => $diasNum,
                'tendencia' => $tendencia,
                'estado_severidad' => $estadoSeveridad,
                'unidad' => 'UN'
            ];
        });

        // Filtros opcionales
        $search = trim((string) $request->input('search', ''));
        $filtroSeveridad = $request->input('filtro_severidad', 'todos');
        $orden = $request->input('orden', 'urgencia');

        $filteredPte = $mappedPte;

        if ($search !== '') {
            $searchLower = mb_strtolower($search);
            $filteredPte = $filteredPte->filter(function ($item) use ($searchLower) {
                return str_contains(mb_strtolower($item['sku']), $searchLower)
                    || str_contains(mb_strtolower($item['nombre']), $searchLower)
                    || str_contains(mb_strtolower($item['detalle']), $searchLower);
            });
        }

        if ($filtroSeveridad && $filtroSeveridad !== 'todos') {
            $filteredPte = $filteredPte->filter(function ($item) use ($filtroSeveridad) {
                if ($filtroSeveridad === 'agotados') return $item['estado_severidad'] === 'agotado';
                if ($filtroSeveridad === 'criticos') return $item['estado_severidad'] === 'critico';
                if ($filtroSeveridad === 'bajo') return $item['estado_severidad'] === 'bajo';
                if ($filtroSeveridad === 'estancados') return $item['estado_severidad'] === 'estancado';
                return true;
            });
        }

        // Ordenamiento
        if ($orden === 'stock_asc') {
            $filteredPte = $filteredPte->sortBy('stock_actual');
        } elseif ($orden === 'stock_desc') {
            $filteredPte = $filteredPte->sortByDesc('stock_actual');
        } elseif ($orden === 'deficit_desc') {
            $filteredPte = $filteredPte->sortByDesc('deficit');
        } elseif ($orden === 'nombre_asc') {
            $filteredPte = $filteredPte->sortBy('nombre');
        } else {
            // Urgencia por defecto: primero menores días (agotados=0 primero), luego mayor déficit
            $filteredPte = $filteredPte->sort(function ($a, $b) {
                if ($a['dias_num'] === $b['dias_num']) {
                    return $b['deficit'] <=> $a['deficit'];
                }
                return $a['dias_num'] <=> $b['dias_num'];
            });
        }

        $filteredPte = $filteredPte->values();
        $pagePte = (int) $request->input('page_pte', 1);
        $totalFilteredPte = $filteredPte->count();
        $paginatedPte = $filteredPte->forPage($pagePte, $perPage)->values();

        $pagPteData = [
            'current_page' => $pagePte,
            'data' => $paginatedPte,
            'last_page' => (int) max(1, ceil($totalFilteredPte / $perPage)),
            'total' => $totalFilteredPte,
            'per_page' => (int) $perPage
        ];

        // --- 2. MP: MATERIAS PRIMAS ---
        $hasMp = Schema::hasTable('mp_materiales');
        $allMp = $hasMp ? MpMaterial::withSum([
            'lotes' => function ($q) {
                $q->where('cantidad_actual', '>', 0);
            }
        ], 'cantidad_actual')
            ->with('unidad')
            ->get()
            ->filter(function ($m) {
                return ($m->lotes_sum_cantidad_actual ?? 0) <= $m->stock_minimo;
            })
            ->values()
            ->map(function ($m) {
                $stockActual = (float) ($m->lotes_sum_cantidad_actual ?? 0);
                $stockMinimo = (float) $m->stock_minimo;
                $deficit = max(0, $stockMinimo - $stockActual);
                $isAgotado = $stockActual <= 0;

                return [
                    'sku' => $m->codigo_interno,
                    'nombre' => $m->nombre_base,
                    'detalle' => 'Materia Prima',
                    'stock_actual' => $stockActual,
                    'stock_critico' => $stockMinimo,
                    'deficit' => $deficit,
                    'unidad' => $m->unidad ? $m->unidad->abreviacion : 'Ud',
                    'dias_para_quiebre' => $isAgotado ? 'Agotado' : 'Bajo Stock Mínimo',
                    'dias_num' => $isAgotado ? 0 : 7,
                    'tendencia' => $isAgotado ? 'critico' : 'normal',
                    'estado_severidad' => $isAgotado ? 'agotado' : 'critico',
                ];
            }) : collect();

        $totalMp = $allMp->count();
        $deficitMp = (float) $allMp->sum('deficit');
        $criticosMp = $allMp->where('estado_severidad', 'agotado')->count();

        $pageMp = (int) $request->input('page_mp', 1);
        $paginatedMp = $allMp->forPage($pageMp, $perPage)->values();

        $pagMpData = [
            'current_page' => $pageMp,
            'data' => $paginatedMp,
            'last_page' => (int) max(1, ceil($totalMp / $perPage)),
            'total' => $totalMp,
            'per_page' => (int) $perPage
        ];

        $kpis = [
            'total_pte' => $totalPte,
            'agotados_pte' => $agotadosPte,
            'riesgo_inminente_pte' => $riesgoInminentePte,
            'stock_bajo_pte' => $stockBajoPte,
            'estancados_pte' => $estancadosPte,
            'deficit_unidades_pte' => $deficitUnidadesPte,
            'total_mp' => $totalMp,
            'criticos_mp' => $criticosMp,
            'deficit_unidades_mp' => $deficitMp,
        ];

        $configData = $this->getConfig()->original;

        return response()->json([
            'kpis' => $kpis,
            'pte' => $pagPteData,
            'mp' => $pagMpData,
            'config' => $configData
        ]);
    }

    // ==========================================
    // 3. ENVÍO DE REPORTES (CORREGIDO)
    // ==========================================

    public function enviarReporte(Request $request)
    {
        try {
            $request->validate([
                'emails' => 'required|array',
                'emails.*' => 'email',
                'tipo' => 'nullable|string'
            ]);

            $tipo = $request->input('tipo', 'todo');
            $pte = null;
            $mp = null;

            // 1. Obtener Datos (Filtrando productos publicados activos)
            if ($tipo === 'todo' || $tipo === 'pte') {
                $pte = PteStock::with(['skuProducto.talla'])
                    ->whereColumn('cantidad', '<=', 'stock_critico')
                    ->whereHas('skuProducto', function ($q) {
                        $q->where('activo', true)
                          ->whereHas('skuGenerico', function ($g) {
                              $g->where('estado_catalogo', 'publicado');
                          });
                    })
                    ->get();
            }

            if ($tipo === 'todo' || $tipo === 'mp') {
                $mp = MpMaterial::withSum([
                    'lotes' => function ($q) {
                        $q->where('cantidad_actual', '>', 0);
                    }
                ], 'cantidad_actual')
                    ->get()
                    ->filter(function ($m) {
                        return ($m->lotes_sum_cantidad_actual ?? 0) <= $m->stock_minimo;
                    });
            }

            // 2. Generar PDF (UNA SOLA VEZ FUERA DEL BUCLE)
            // Esto soluciona el error 500 al enviar múltiples correos
            $pdf = Pdf::loadView('emails.alerta_stock', [
                'pte' => $pte,
                'mp' => $mp,
                'fecha' => now()->format('d/m/Y H:i'),
                'tipo' => $tipo
            ]);

            // Renderizamos el binario del PDF aquí para no re-procesarlo en cada vuelta
            $pdfContent = $pdf->output();

            // 3. Enviar Bucle usando el contenido ya generado
            foreach ($request->emails as $email) {
                // Usamos trim para evitar errores de espacios en blanco
                $destinatario = trim($email);

                // Pasamos $pdfContent y una variable fecha en el use()
                $fechaStr = now()->format('Y-m-d_H-i');
                Mail::send('emails.alerta_stock_cuerpo', ['fecha' => now()->format('d/m/Y H:i')], function ($message) use ($destinatario, $pdfContent, $fechaStr) {
                    $message->to($destinatario)
                        ->subject('🚨 Reporte de Stock Crítico - Tienda Bomberos')
                        ->attachData($pdfContent, "reporte_alertas_{$fechaStr}.pdf", [
                            'mime' => 'application/pdf',
                        ]);
                });
            }

            return response()->json(['message' => 'Reporte enviado con éxito']);

        } catch (\Exception $e) {
            Log::error("Error enviando reporte: " . $e->getMessage());
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}