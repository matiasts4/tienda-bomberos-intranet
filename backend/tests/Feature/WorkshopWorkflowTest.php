<?php

namespace Tests\Feature;

use App\Models\Compania;
use App\Models\Cotizacion;
use App\Models\Cuerpo;
use App\Models\DetalleCotizacion;
use App\Models\Estado;
use App\Models\HistorialEtapa;
use App\Models\OrdenCorte;
use Tests\TestCase;

/**
 * Suite de pruebas de integración para el flujo operativo de taller (RF-06 y RF-08).
 * Valida la generación de órdenes de corte desde cotizaciones aprobadas, la
 * integridad de ítems/personalizaciones y la máquina de estados con auditoría.
 */
class WorkshopWorkflowTest extends TestCase
{
    /**
     * Helper para preparar una cotización aprobada con cuerpo, compañía e ítems personalizados.
     */
    private function setupApprovedQuoteWithPersonalization(): Cotizacion
    {
        $user = $this->actingAsAdmin();

        Cuerpo::create([
            'id' => '1300100',
            'nombre' => 'Cuerpo de Bomberos de Santiago',
        ]);

        Compania::create([
            'id' => '1300114',
            'cuerpo_id' => '1300100',
            'nombre' => '14ª Compañía - The British and Commonwealth Fire Company',
            'numero' => 14,
        ]);

        $estadoAprobada = Estado::create([
            'nombre' => 'Aprobada por Cliente',
            'scope' => 'cotizacion',
            'orden' => 2,
            'color' => '#16a34a',
        ]);

        $quote = Cotizacion::factory()
            ->forUser($user)
            ->withEstado($estadoAprobada)
            ->create([
                'cuerpo_id' => '1300100',
                'compania_id' => '1300114',
                'numero' => 'CT-2026-W01',
                'nombre' => 'Uniformes Estructurales 14ª Compañía',
                'fecha_venta' => '2026-11-20',
                'plazo_produccion' => '20 dias',
            ]);

        DetalleCotizacion::factory()
            ->forCotizacion($quote)
            ->create([
                'sku' => 'PARK-EXT-001',
                'producto' => 'Parka de Rescate Técnico Normada',
                'talla' => 'L',
                'color' => 'Rojo Alta Visibilidad',
                'cantidad' => 8,
                'is_personalizable' => true,
                'tipo_personalizacion' => 'bordado',
                'configuracion' => [
                    'fabric_type' => 'Nomex Ripstop Antiestático',
                    'grupos' => [
                        [
                            'nombre_grupo' => 'Oficiales de Mando',
                            'tallas' => [
                                ['talla' => 'M', 'color' => 'Rojo', 'cantidad' => 3],
                                ['talla' => 'L', 'color' => 'Rojo', 'cantidad' => 5],
                            ],
                            'matrices' => [
                                [
                                    'posicion' => 'Espalda Superior',
                                    'tecnica' => 'Bordado Reflectante',
                                    'valor_fijo' => 'BOMBEROS SANTIAGO 14',
                                ],
                            ],
                            'variables' => [],
                        ],
                    ],
                ],
            ]);

        return $quote;
    }

    /**
     * Caso 1 (RF-06): Una cotización en estado borrador NO puede generar una orden de corte.
     * La solicitud debe ser rechazada con código HTTP 422 y no crear registros en la base de datos.
     */
    public function test_draft_quote_cannot_generate_cutting_order_and_fails_with_422(): void
    {
        $user = $this->actingAsAdmin();

        $estadoBorrador = Estado::create([
            'nombre' => 'Borrador',
            'scope' => 'cotizacion',
            'orden' => 1,
            'color' => '#64748b',
        ]);

        $draftQuote = Cotizacion::factory()
            ->forUser($user)
            ->withEstado($estadoBorrador)
            ->create([
                'numero' => 'CT-DRAFT-999',
                'nombre' => 'Cotización Preliminar en Borrador',
            ]);

        DetalleCotizacion::factory()
            ->forCotizacion($draftQuote)
            ->create([
                'producto' => 'Chaqueta de Trabajo Básica',
                'talla' => 'M',
                'color' => 'Negro',
                'cantidad' => 6,
            ]);

        $response = $this->postJson("/api/cutting-orders/from-quote/{$draftQuote->id}");

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertSame(0, OrdenCorte::count());
    }

    /**
     * Caso 2 (RF-06): Una cotización aprobada genera exitosamente una orden de corte.
     * Comprueba la transferencia íntegra de ítems, tallas, personalizaciones y estado inicial 'corte'.
     */
    public function test_approved_quote_successfully_generates_cutting_order_with_items_and_customization(): void
    {
        $quote = $this->setupApprovedQuoteWithPersonalization();

        $response = $this->postJson("/api/cutting-orders/from-quote/{$quote->id}");

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.cotizacion_id', $quote->id);
        $response->assertJsonPath('data.cuerpo_id', '1300100');
        $response->assertJsonPath('data.compania_id', '1300114');
        $response->assertJsonPath('data.status', OrdenCorte::STAGE_CORTE);
        $response->assertJsonPath('data.total_unidades', 8);

        $orderId = $response->json('data.id');
        $items = $response->json('data.items');
        $this->assertCount(2, $items);
        $this->assertSame('M', $items[0]['size']);
        $this->assertSame(3, (int) $items[0]['quantity']);
        $this->assertSame('L', $items[1]['size']);
        $this->assertSame(5, (int) $items[1]['quantity']);

        // Verificación de persistencia de personalización institucional en la orden
        $this->assertSame('bordado', $items[0]['personalizacion']['tipo_personalizacion']);
        $this->assertSame('Oficiales de Mando', $items[0]['personalizacion']['grupo']);

        // Verificación del registro inicial de auditoría en historial_etapas
        $this->assertDatabaseHas('historial_etapas', [
            'model_type' => OrdenCorte::class,
            'model_id' => $orderId,
            'etapa_nueva' => OrdenCorte::STAGE_CORTE,
        ]);
    }

    /**
     * Caso 3 (RF-07 y RF-08): La transición de etapas respeta estrictamente la máquina de estados.
     * Flujo secuencial Corte -> Confección -> Empaque -> Terminado y auditoría con usuario autenticado.
     */
    public function test_stage_transitions_respect_state_machine_and_log_history_with_authenticated_user(): void
    {
        $quote = $this->setupApprovedQuoteWithPersonalization();
        $operator = $this->actingAsEjecutivo(['name' => 'Operario Textil Especializado']);

        $creationResponse = $this->postJson("/api/cutting-orders/from-quote/{$quote->id}")->assertCreated();
        $orderId = $creationResponse->json('data.id');

        // 1. Validar que un salto inválido directo a Empaque sea bloqueado con 422
        $invalidJump = $this->patchJson("/api/cutting-orders/{$orderId}/stage", [
            'stage' => OrdenCorte::STAGE_EMPAQUE,
        ]);
        $invalidJump->assertStatus(422);
        $invalidJump->assertJsonPath('success', false);

        // 2. Transición válida: Corte -> Confección
        $step1 = $this->patchJson("/api/cutting-orders/{$orderId}/stage", [
            'stage' => OrdenCorte::STAGE_CONFECCION,
            'observaciones' => 'Piezas cortadas según patrón y despachadas a línea de costura.',
        ]);
        $step1->assertStatus(200);
        $step1->assertJsonPath('data.status', OrdenCorte::STAGE_CONFECCION);

        // 3. Validar que un retroceso no permitido a Corte sea bloqueado con 422
        $invalidBackward = $this->patchJson("/api/cutting-orders/{$orderId}/stage", [
            'stage' => OrdenCorte::STAGE_CORTE,
        ]);
        $invalidBackward->assertStatus(422);

        // 4. Transición válida: Confección -> Empaque
        $step2 = $this->patchJson("/api/cutting-orders/{$orderId}/stage", [
            'stage' => OrdenCorte::STAGE_EMPAQUE,
            'observaciones' => 'Costura y bordados institucionales finalizados con control dimensional.',
        ]);
        $step2->assertStatus(200);
        $step2->assertJsonPath('data.status', OrdenCorte::STAGE_EMPAQUE);

        // 5. Transición válida: Empaque -> Terminado
        $step3 = $this->patchJson("/api/cutting-orders/{$orderId}/stage", [
            'stage' => OrdenCorte::STAGE_TERMINADO,
            'observaciones' => 'Prendas etiquetadas, embolsadas y listas para entrega.',
        ]);
        $step3->assertStatus(200);
        $step3->assertJsonPath('data.status', OrdenCorte::STAGE_TERMINADO);

        // Verificación de auditoría en historial_etapas con usuario autenticado
        $historyRecords = HistorialEtapa::where('model_type', OrdenCorte::class)
            ->where('model_id', $orderId)
            ->get();

        $this->assertCount(4, $historyRecords);

        $this->assertDatabaseHas('historial_etapas', [
            'model_type' => OrdenCorte::class,
            'model_id' => $orderId,
            'user_id' => $operator->id,
            'etapa_anterior' => OrdenCorte::STAGE_CORTE,
            'etapa_nueva' => OrdenCorte::STAGE_CONFECCION,
            'observaciones' => 'Piezas cortadas según patrón y despachadas a línea de costura.',
        ]);

        $this->assertDatabaseHas('historial_etapas', [
            'model_type' => OrdenCorte::class,
            'model_id' => $orderId,
            'user_id' => $operator->id,
            'etapa_anterior' => OrdenCorte::STAGE_CONFECCION,
            'etapa_nueva' => OrdenCorte::STAGE_EMPAQUE,
            'observaciones' => 'Costura y bordados institucionales finalizados con control dimensional.',
        ]);
    }
}
