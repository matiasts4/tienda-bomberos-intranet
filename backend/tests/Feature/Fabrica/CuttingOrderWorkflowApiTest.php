<?php

namespace Tests\Feature\Fabrica;

use App\Models\Compania;
use App\Models\Cotizacion;
use App\Models\Cuerpo;
use App\Models\DetalleCotizacion;
use App\Models\Estado;
use App\Models\FactoryLog;
use App\Models\HistorialEtapa;
use App\Models\OrdenCorte;
use Tests\TestCase;

class CuttingOrderWorkflowApiTest extends TestCase
{
    private function createApprovedQuoteWithDetails(): Cotizacion
    {
        $user = $this->actingAsAdmin();

        Cuerpo::create([
            'id' => '1300100',
            'nombre' => 'Santiago',
        ]);

        Compania::create([
            'id' => '1300114',
            'cuerpo_id' => '1300100',
            'nombre' => '14ª Compañía',
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
                'cliente_id' => null,
                'cuerpo_id' => '1300100',
                'compania_id' => '1300114',
                'numero' => 'CT-2026-014',
                'nombre' => 'Casacas Institucionales 14ª Cía',
                'fecha_venta' => '2026-11-15',
                'plazo_produccion' => '15 dias',
            ]);

        DetalleCotizacion::factory()
            ->forCotizacion($quote)
            ->create([
                'sku' => 'CAS-INST-001',
                'producto' => 'Casaca Institucional Bomberil',
                'talla' => 'L',
                'color' => 'Azul Marino',
                'cantidad' => 12,
                'is_personalizable' => true,
                'tipo_personalizacion' => 'bordado',
                'configuracion' => [
                    'fabric_type' => 'Ripstop Ignífugo',
                    'grupos' => [
                        [
                            'nombre_grupo' => 'Brigada 14ª',
                            'tallas' => [
                                ['talla' => 'M', 'color' => 'Azul Marino', 'cantidad' => 5],
                                ['talla' => 'L', 'color' => 'Azul Marino', 'cantidad' => 7],
                            ],
                            'matrices' => [
                                [
                                    'posicion' => 'Pecho Izquierdo',
                                    'tecnica' => 'Bordado',
                                    'valor_fijo' => '14ª Cía Santiago',
                                ],
                            ],
                            'variables' => [],
                        ],
                    ],
                ],
            ]);

        return $quote;
    }

    public function test_from_quote_requires_authentication(): void
    {
        $this->postJson('/api/cutting-orders/from-quote/1')->assertUnauthorized();
    }

    public function test_from_quote_returns_404_when_quote_does_not_exist(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/cutting-orders/from-quote/999999')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_from_quote_rejects_draft_or_unapproved_quote_with_422(): void
    {
        $user = $this->actingAsAdmin();

        $estadoBorrador = Estado::create([
            'nombre' => 'Borrador',
            'scope' => 'cotizacion',
            'orden' => 1,
            'color' => '#64748b',
        ]);

        $quote = Cotizacion::factory()
            ->forUser($user)
            ->withEstado($estadoBorrador)
            ->create();

        DetalleCotizacion::factory()->forCotizacion($quote)->create([
            'producto' => 'Pantalón Faena',
            'talla' => 'M',
            'color' => 'Azul',
            'cantidad' => 4,
        ]);

        $this->postJson("/api/cutting-orders/from-quote/{$quote->id}")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(0, OrdenCorte::count());
    }

    public function test_from_quote_creates_cutting_order_with_items_personalization_and_initial_logs(): void
    {
        $quote = $this->createApprovedQuoteWithDetails();

        $response = $this->postJson("/api/cutting-orders/from-quote/{$quote->id}")
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cotizacion_id', $quote->id)
            ->assertJsonPath('data.compania_id', '1300114')
            ->assertJsonPath('data.cuerpo_id', '1300100')
            ->assertJsonPath('data.status', OrdenCorte::STAGE_CORTE)
            ->assertJsonPath('data.total_unidades', 12);

        $orderId = $response->json('data.id');
        $this->assertCount(2, $response->json('data.items'));
        $this->assertSame('M', $response->json('data.items.0.size'));
        $this->assertSame(5, $response->json('data.items.0.quantity'));
        $this->assertSame('bordado', $response->json('data.items.0.personalizacion.tipo_personalizacion'));

        $this->assertDatabaseHas('historial_etapas', [
            'model_type' => OrdenCorte::class,
            'model_id' => $orderId,
            'etapa_nueva' => OrdenCorte::STAGE_CORTE,
        ]);

        $this->assertDatabaseHas('logs_fabrica', [
            'entity_type' => 'CuttingOrder',
            'entity_id' => $orderId,
            'action' => 'Orden Generada desde Cotización',
        ]);
    }

    public function test_index_filters_cutting_orders_by_stage(): void
    {
        $this->actingAsAdmin();

        OrdenCorte::factory()->inStage(OrdenCorte::STAGE_CORTE)->create();
        OrdenCorte::factory()->inStage(OrdenCorte::STAGE_CONFECCION)->create();
        OrdenCorte::factory()->inStage(OrdenCorte::STAGE_EMPAQUE)->create();

        $response = $this->getJson('/api/cutting-orders?stage=confeccion')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('data'));
        $this->assertSame(OrdenCorte::STAGE_CONFECCION, $response->json('data.0.status'));
    }

    public function test_stage_transitions_enforce_state_machine_and_record_audit_history(): void
    {
        $quote = $this->createApprovedQuoteWithDetails();
        $operator = $this->actingAsEjecutivo(['name' => 'Operario Planta']);

        $created = $this->postJson("/api/cutting-orders/from-quote/{$quote->id}")->assertCreated();
        $orderId = $created->json('data.id');

        // Salto inválido corte -> empaque debe fallar con 422
        $this->patchJson("/api/cutting-orders/{$orderId}/stage", [
            'stage' => OrdenCorte::STAGE_EMPAQUE,
        ])->assertStatus(422)
          ->assertJsonPath('success', false);

        // Transición válida corte -> confeccion
        $toSewing = $this->patchJson("/api/cutting-orders/{$orderId}/stage", [
            'stage' => OrdenCorte::STAGE_CONFECCION,
            'observaciones' => 'Corte finalizado sin merma',
        ])->assertOk()
          ->assertJsonPath('data.status', OrdenCorte::STAGE_CONFECCION);
        $this->assertEquals(50, (float) $toSewing->json('data.progress'));

        // Retroceso inválido confeccion -> corte debe fallar con 422
        $this->patchJson("/api/cutting-orders/{$orderId}/stage", [
            'stage' => OrdenCorte::STAGE_CORTE,
        ])->assertStatus(422);

        // Transición válida confeccion -> empaque
        $toPacking = $this->patchJson("/api/cutting-orders/{$orderId}/stage", [
            'stage' => OrdenCorte::STAGE_EMPAQUE,
            'observaciones' => 'Confección completada y revisada',
        ])->assertOk()
          ->assertJsonPath('data.status', OrdenCorte::STAGE_EMPAQUE);
        $this->assertEquals(75, (float) $toPacking->json('data.progress'));

        // Transición válida empaque -> terminado
        $toFinished = $this->patchJson("/api/cutting-orders/{$orderId}/stage", [
            'stage' => OrdenCorte::STAGE_TERMINADO,
        ])->assertOk()
          ->assertJsonPath('data.status', OrdenCorte::STAGE_TERMINADO);
        $this->assertEquals(100, (float) $toFinished->json('data.progress'));

        $this->assertSame(
            4,
            HistorialEtapa::where('model_type', OrdenCorte::class)
                ->where('model_id', $orderId)
                ->count()
        );

        $this->assertDatabaseHas('historial_etapas', [
            'model_type' => OrdenCorte::class,
            'model_id' => $orderId,
            'user_id' => $operator->id,
            'etapa_anterior' => OrdenCorte::STAGE_CORTE,
            'etapa_nueva' => OrdenCorte::STAGE_CONFECCION,
            'observaciones' => 'Corte finalizado sin merma',
        ]);

        $logsResponse = $this->getJson("/api/cutting-orders/{$orderId}/logs")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cutting_order_id', $orderId)
            ->assertJsonPath('data.current_stage', OrdenCorte::STAGE_TERMINADO);

        $this->assertCount(4, $logsResponse->json('data.stage_history'));
        $this->assertCount(4, $logsResponse->json('data.factory_logs'));
        $this->assertSame(
            4,
            FactoryLog::where('entity_type', 'CuttingOrder')->where('entity_id', $orderId)->count()
        );
    }
}
