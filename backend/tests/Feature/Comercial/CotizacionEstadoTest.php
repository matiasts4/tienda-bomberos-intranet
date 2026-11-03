<?php

namespace Tests\Feature\Comercial;

use App\Models\Cotizacion;
use App\Models\CotizacionArchivo;
use App\Models\Estado;
use App\Models\HistorialEtapa;
use Tests\TestCase;

class CotizacionEstadoTest extends TestCase
{
    public function test_cambia_estado_y_registra_en_historial(): void
    {
        $estados = $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)
            ->withEstado($estados['Cotización Enviada'])->create();

        // Estado intermedio cualquiera (no exige docs)
        $otro = Estado::factory()->cotizacion('En negociación')->create();

        $this->patchJson("/api/cotizaciones/{$cot->id}/estado", [
            'estado_id' => $otro->id,
        ])->assertOk();

        $this->assertSame($otro->id, $cot->fresh()->estado_id);
        $this->assertDatabaseHas('historial_etapas', [
            'model_type' => Cotizacion::class,
            'model_id' => $cot->id,
            'estado_anterior_id' => $estados['Cotización Enviada']->id,
            'estado_nuevo_id' => $otro->id,
        ]);
    }

    public function test_rechaza_estado_de_otro_scope(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create();
        $estadoOportunidad = Estado::factory()->oportunidad('Ganada')->create();

        $this->patchJson("/api/cotizaciones/{$cot->id}/estado", [
            'estado_id' => $estadoOportunidad->id,
        ])->assertStatus(422)
          ->assertJsonPath('message', 'El estado enviado no pertenece al scope cotizacion.');
    }

    public function test_aprobada_requiere_documento_adjunto(): void
    {
        $estados = $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create();

        $this->patchJson("/api/cotizaciones/{$cot->id}/estado", [
            'estado_id' => $estados['Aprobada por Cliente']->id,
        ])->assertStatus(422)
          ->assertJsonPath('code', 'MISSING_DOCS');
    }

    public function test_aprobada_funciona_con_documento_adjunto(): void
    {
        $estados = $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create();

        CotizacionArchivo::factory()
            ->tipo('orden_venta')
            ->create(['cotizacion_id' => $cot->id]);

        $this->patchJson("/api/cotizaciones/{$cot->id}/estado", [
            'estado_id' => $estados['Aprobada por Cliente']->id,
        ])->assertOk();

        $this->assertSame($estados['Aprobada por Cliente']->id, $cot->fresh()->estado_id);
    }

    public function test_rechazada_requiere_motivo(): void
    {
        $estados = $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create();

        $this->patchJson("/api/cotizaciones/{$cot->id}/estado", [
            'estado_id' => $estados['Rechazada']->id,
        ])->assertStatus(422)
          ->assertJsonPath('code', 'MISSING_REASON');
    }

    public function test_rechazada_persiste_motivo_y_fecha(): void
    {
        $estados = $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create();

        $this->patchJson("/api/cotizaciones/{$cot->id}/estado", [
            'estado_id' => $estados['Rechazada']->id,
            'motivo_rechazo' => 'Precio fuera de presupuesto',
        ])->assertOk();

        $cot->refresh();
        $this->assertSame('Precio fuera de presupuesto', $cot->motivo_rechazo);
        $this->assertNotNull($cot->fecha_rechazo);
    }

    public function test_no_registra_historial_si_estado_no_cambia(): void
    {
        $estados = $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)
            ->withEstado($estados['Cotización Enviada'])->create();

        $this->patchJson("/api/cotizaciones/{$cot->id}/estado", [
            'estado_id' => $estados['Cotización Enviada']->id,
        ])->assertOk();

        $this->assertSame(0, HistorialEtapa::where('model_id', $cot->id)->count());
    }

    public function test_pasar_a_ventas_registra_fecha_venta_y_fecha_entrega(): void
    {
        $this->travelTo('2026-10-06');
        $estados = $this->seedEstadosCotizacion();
        $ventas = Estado::factory()->cotizacion('Ventas')->create();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)
            ->withEstado($estados['Cotización Enviada'])
            ->create(['plazo_produccion' => '20 dias']);
        CotizacionArchivo::factory()->tipo('orden_venta')->create(['cotizacion_id' => $cot->id]);

        $this->patchJson("/api/cotizaciones/{$cot->id}/estado", [
            'estado_id' => $ventas->id,
        ])->assertOk()
          ->assertJsonPath('cotizacion.fecha_entrega', '2026-10-26');

        $this->assertSame('2026-10-06', $cot->fresh()->fecha_venta->toDateString());
    }

    public function test_mover_entre_estados_de_venta_conserva_fecha_venta(): void
    {
        $this->travelTo('2026-10-06');
        $estados = $this->seedEstadosCotizacion();
        $ventas = Estado::factory()->cotizacion('Ventas')->create();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)
            ->withEstado($estados['Aprobada por Cliente'])
            ->create(['fecha_venta' => '2026-09-01']);
        CotizacionArchivo::factory()->tipo('orden_venta')->create(['cotizacion_id' => $cot->id]);

        $this->patchJson("/api/cotizaciones/{$cot->id}/estado", [
            'estado_id' => $ventas->id,
        ])->assertOk();

        $this->assertSame('2026-09-01', $cot->fresh()->fecha_venta->toDateString());
    }
}
