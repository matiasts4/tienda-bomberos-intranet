<?php

namespace Tests\Feature\Comercial;

use App\Models\Cotizacion;
use App\Models\DetalleCotizacion;

use App\Models\Personalizacion;
use Tests\TestCase;

class CotizacionSendToPersonalizacionTest extends TestCase
{
    private function setupConfigured(string $tipo = 'embroidery'): array
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsUser(['role' => 'superadmin']);
        $cot = Cotizacion::factory()->forUser($admin)->withEstado(\App\Models\Estado::where('nombre', 'Aprobada por Cliente')->firstOrFail())->create([
            'estado_personalizacion' => 'configured',
        ]);
        
        // Configurar al menos un grupo en la columna JSON (sino, sendToPersonalizacion lo salta)
        $config = [
            [
                'nombre_grupo' => 'G1',
                'matrices' => array_map(fn ($technique) => [
                    'posicion' => 'Frente', 'tecnica' => $technique,
                    'tipo_contenido' => 'texto_fijo', 'valor_fijo' => 'BOMBEROS',
                ], $tipo === 'bordado' ? ['Bordado'] : ['Bordado', 'Estampado']),
                'tallas' => [
                    ['genero' => 'M', 'color' => 'Negro', 'talla' => 'M', 'cantidad' => 3]
                ]
            ]
        ];

        $item = DetalleCotizacion::factory()->forCotizacion($cot)->create([
            'tipo_personalizacion' => $tipo,
            'sku' => '1234567890', // 10 chars genérico
            'producto' => 'Polera',
            'cantidad' => 3,
            'n_item' => 1,
            'configuracion' => $config,
        ]);

        return [$cot, $item];
    }

    public function test_falla_si_no_esta_en_estado_configured(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsUser(['role' => 'superadmin']);
        $cot = Cotizacion::factory()->forUser($admin)->withEstado(\App\Models\Estado::where('nombre', 'Aprobada por Cliente')->firstOrFail())->create([
            'estado_personalizacion' => null,
        ]);

        $this->postJson("/api/cotizaciones/{$cot->id}/send-personalizacion")
            ->assertStatus(422)
            ->assertJsonPath('message', 'La cotización no está configurada o ya fue enviada.');
    }

    public function test_envia_items_y_marca_cotizacion_como_sent(): void
    {
        [$cot, $item] = $this->setupConfigured('bordado');

        $response = $this->postJson("/api/cotizaciones/{$cot->id}/send-personalizacion")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertStringContainsString('Se enviaron 1 ítem', $response->json('message'));
        $this->assertSame('sent_to_personalization', $cot->fresh()->estado_personalizacion);

        $personalizacion = Personalizacion::first();
        $this->assertSame($cot->id, $personalizacion->cotizacion_id);
        $this->assertSame('bordado', $personalizacion->tipo);
        $this->assertSame('pendiente_diseno', $personalizacion->estado);
        $this->assertSame(3, $personalizacion->cantidad);
        $this->assertSame(1, $personalizacion->numero_item);
        $this->assertNull($personalizacion->siguiente_proceso);
    }

    public function test_setea_siguiente_proceso_para_print_then_embroidery(): void
    {
        [$cot, $item] = $this->setupConfigured('print_then_embroidery');

        $this->postJson("/api/cotizaciones/{$cot->id}/send-personalizacion")->assertOk();

        $p = Personalizacion::first();
        $this->assertSame('estampado', $p->tipo);
        $this->assertSame('bordado', $p->siguiente_proceso);
    }

    public function test_setea_siguiente_proceso_para_embroidery_then_print(): void
    {
        [$cot, $item] = $this->setupConfigured('embroidery_then_print');

        $this->postJson("/api/cotizaciones/{$cot->id}/send-personalizacion")->assertOk();

        $p = Personalizacion::first();
        $this->assertSame('bordado', $p->tipo);
        $this->assertSame('estampado', $p->siguiente_proceso);
    }

    public function test_mixed_marca_para_decision_en_hub(): void
    {
        [$cot, $item] = $this->setupConfigured('mixed');

        $this->postJson("/api/cotizaciones/{$cot->id}/send-personalizacion")->assertOk();

        $p = Personalizacion::first();
        $this->assertSame('mixto', $p->tipo);
        $this->assertSame('mixto_pendiente', $p->siguiente_proceso);
    }

    public function test_omite_items_con_tipo_none(): void
    {
        $this->seedEstadosCotizacion();
        \App\Models\Estado::create(['nombre' => 'Por despachar', 'scope' => 'despacho', 'orden' => 1]);
        $admin = $this->actingAsUser(['role' => 'superadmin']);
        $cot = Cotizacion::factory()->forUser($admin)->withEstado(\App\Models\Estado::where('nombre', 'Aprobada por Cliente')->firstOrFail())->create([
            'estado_personalizacion' => 'configured',
        ]);

        // Item con tipo none + grupo configurado → debe saltarse
        $itemNone = DetalleCotizacion::factory()->forCotizacion($cot)->create([
            'tipo_personalizacion' => 'none',
            'producto' => 'Polera Sin Personalización',
            'configuracion' => [['nombre_grupo' => 'X', 'tallas' => [['talla' => 'M', 'cantidad' => 1]]]],
        ]);

        $response = $this->postJson("/api/cotizaciones/{$cot->id}/send-personalizacion")
            ->assertOk();

        $this->assertStringContainsString('Entrega Directa', $response->json('message'));
        $this->assertSame('direct_delivery', $cot->fresh()->estado_personalizacion);
        $this->assertTrue($cot->fresh()->personalizacion_completada);
        $this->assertSame(0, Personalizacion::count());
    }

    public function test_rechaza_envio_de_items_sin_configurar(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsUser(['role' => 'superadmin']);
        $cot = Cotizacion::factory()->forUser($admin)->withEstado(\App\Models\Estado::where('nombre', 'Aprobada por Cliente')->firstOrFail())->create([
            'estado_personalizacion' => 'configured',
        ]);

        // Item sin grupos (configuracion null)
        DetalleCotizacion::factory()->forCotizacion($cot)->create([
            'tipo_personalizacion' => 'bordado',
            'configuracion' => null,
        ]);

        $this->postJson("/api/cotizaciones/{$cot->id}/send-personalizacion")
            ->assertStatus(422);

        $this->assertSame(0, Personalizacion::count());
    }
}
