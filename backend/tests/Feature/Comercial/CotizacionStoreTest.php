<?php

namespace Tests\Feature\Comercial;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Estado;
use Tests\TestCase;

class CotizacionStoreTest extends TestCase
{
    public function test_requiere_autenticacion(): void
    {
        $this->seedEstadosCotizacion();
        $cliente = Cliente::factory()->create();

        $this->postJson('/api/cotizaciones', ['cliente_id' => $cliente->id])
            ->assertUnauthorized();
    }

    public function test_valida_cliente_id_requerido(): void
    {
        $this->actingAsEjecutivo();
        $this->seedEstadosCotizacion();

        $this->postJson('/api/cotizaciones', ['nombre' => 'Cotización de prueba'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cliente_id']);
    }

    public function test_valida_cliente_inexistente(): void
    {
        $this->actingAsEjecutivo();
        $this->seedEstadosCotizacion();

        $this->postJson('/api/cotizaciones', ['cliente_id' => 'NO_EXISTE'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cliente_id']);
    }

    public function test_crea_cotizacion_con_defaults(): void
    {
        $user = $this->actingAsEjecutivo();
        $estados = $this->seedEstadosCotizacion();
        $cliente = Cliente::factory()->create();

        $response = $this->postJson('/api/cotizaciones', [
            'cliente_id' => $cliente->id,
            'nombre' => 'Cotización de prueba',
        ])->assertCreated();

        $cot = Cotizacion::first();
        $this->assertSame($cliente->id, $cot->cliente_id);
        $this->assertSame($user->id, $cot->user_id);
        $this->assertSame($estados['Cotización Enviada']->id, $cot->estado_id);
        $this->assertSame('clientes', $cot->origen);
        $this->assertSame(0, (int) $cot->total_neto);
        // Valores default del controller (CotizacionController::store)
        $this->assertSame('20 dias', $cot->plazo_produccion);
        $this->assertSame('Contado - Transferencia', $cot->condiciones_pago);
        $this->assertSame('Por pagar', $cot->despacho);
        $this->assertNotNull($cot->fecha_creacion);
        $this->assertNotNull($cot->fecha_vencimiento);

        // Numero auto-generado
        $response->assertJsonPath('numero', 'CT-' . str_pad((string) $cot->id, 4, '0', STR_PAD_LEFT));
    }

    public function test_falla_si_no_existe_estado_cotizacion_enviada(): void
    {
        $this->actingAsEjecutivo();
        // No seedeamos estados a propósito
        $cliente = Cliente::factory()->create();

        $this->postJson('/api/cotizaciones', ['cliente_id' => $cliente->id, 'nombre' => 'Cotización de prueba'])
            ->assertStatus(500)
            ->assertJsonPath(
                'message',
                'No existe el estado "Cotización Enviada" con scope=cotizacion.'
            );
    }

    public function test_solo_acepta_estados_con_scope_cotizacion(): void
    {
        $this->actingAsEjecutivo();
        // Estado con scope distinto NO debe ser elegido como default
        Estado::factory()->oportunidad('Cotización Enviada')->create();
        $cliente = Cliente::factory()->create();

        $this->postJson('/api/cotizaciones', ['cliente_id' => $cliente->id, 'nombre' => 'Cotización de prueba'])
            ->assertStatus(500);
    }
}
