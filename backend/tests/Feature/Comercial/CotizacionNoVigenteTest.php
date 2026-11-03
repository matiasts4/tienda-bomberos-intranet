<?php

namespace Tests\Feature\Comercial;

use App\Models\Cotizacion;
use App\Models\Estado;
use App\Support\NoVigente;
use Tests\TestCase;

/**
 * Etapa "No Vigentes": al arrastrar una tarjeta se exige motivo y observación.
 */
class CotizacionNoVigenteTest extends TestCase
{
    private function cotizacion(string $estado = 'Cotización Enviada', array $attrs = []): Cotizacion
    {
        $e = Estado::firstOrCreate(['scope' => 'cotizacion', 'nombre' => $estado], ['orden' => 1]);
        return Cotizacion::factory()->forUser($this->user)->withEstado($e)->create($attrs);
    }

    private function moverANoVigente(Cotizacion $cot, array $payload)
    {
        return $this->patchJson("/api/cotizaciones/{$cot->id}/estado", array_merge([
            'estado_id' => NoVigente::estado()->id,
        ], $payload));
    }

    private $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->actingAsEjecutivo();
    }

    public function test_desistida_guarda_motivo_observacion_responsable_e_historial(): void
    {
        $this->travelTo('2026-10-25 10:00:00');
        $cot = $this->cotizacion();

        $this->moverANoVigente($cot, [
            'motivo_no_vigente' => 'desistida',
            'observacion_no_vigente' => 'El directorio no aprobó el presupuesto.',
        ])->assertOk()
          ->assertJsonPath('cotizacion.motivo_no_vigente', 'desistida')
          ->assertJsonPath('cotizacion.no_vigente_por.name', $this->user->name);

        $cot->refresh();
        $this->assertSame(NoVigente::estado()->id, $cot->estado_id);
        $this->assertSame('El directorio no aprobó el presupuesto.', $cot->observacion_no_vigente);
        $this->assertNull($cot->motivo_no_vigente_otro);
        $this->assertSame('2026-10-25 10:00:00', $cot->fecha_no_vigente->toDateTimeString());
        $this->assertDatabaseHas('historial_etapas', [
            'model_id' => $cot->id,
            'estado_nuevo_id' => NoVigente::estado()->id,
            'observaciones' => 'Desistida: El directorio no aprobó el presupuesto.',
        ]);
    }

    public function test_exige_motivo_valido_y_observacion(): void
    {
        $cot = $this->cotizacion();

        $this->moverANoVigente($cot, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['motivo_no_vigente', 'observacion_no_vigente']);

        $this->moverANoVigente($cot, ['motivo_no_vigente' => 'perdida', 'observacion_no_vigente' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['motivo_no_vigente']);

        // Solo espacios: TrimStrings lo deja vacío y cuenta como no escrito.
        $this->moverANoVigente($cot, ['motivo_no_vigente' => 'desistida', 'observacion_no_vigente' => '   '])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['observacion_no_vigente']);

        $this->moverANoVigente($cot, [
            'motivo_no_vigente' => 'desistida',
            'observacion_no_vigente' => str_repeat('a', NoVigente::MAX_OBSERVACION + 1),
        ])->assertStatus(422)->assertJsonValidationErrors(['observacion_no_vigente']);

        $this->assertSame('Cotización Enviada', $cot->fresh()->estado->nombre);
    }

    public function test_otro_exige_descripcion_breve(): void
    {
        $cot = $this->cotizacion();

        $this->moverANoVigente($cot, ['motivo_no_vigente' => 'otro', 'observacion_no_vigente' => 'Detalle'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['motivo_no_vigente_otro']);

        $this->moverANoVigente($cot, [
            'motivo_no_vigente' => 'otro',
            'motivo_no_vigente_otro' => str_repeat('a', NoVigente::MAX_OTRO + 1),
            'observacion_no_vigente' => 'Detalle',
        ])->assertStatus(422)->assertJsonValidationErrors(['motivo_no_vigente_otro']);

        $this->moverANoVigente($cot, [
            'motivo_no_vigente' => 'otro',
            'motivo_no_vigente_otro' => 'Cambio de proveedor',
            'observacion_no_vigente' => 'Se fue con la competencia.',
        ])->assertOk();

        $this->assertSame('Cambio de proveedor', $cot->fresh()->motivo_no_vigente_otro);
        $this->assertDatabaseHas('historial_etapas', [
            'model_id' => $cot->id,
            'observaciones' => 'Otro (Cambio de proveedor): Se fue con la competencia.',
        ]);
    }

    public function test_la_descripcion_de_otro_no_se_guarda_con_otro_motivo(): void
    {
        $cot = $this->cotizacion();

        $this->moverANoVigente($cot, [
            'motivo_no_vigente' => 'anulada',
            'motivo_no_vigente_otro' => 'texto que sobra',
            'observacion_no_vigente' => 'Duplicada',
        ])->assertOk();

        $this->assertNull($cot->fresh()->motivo_no_vigente_otro);
    }

    public function test_finalizacion_administrativa_solo_superadmin(): void
    {
        $cot = $this->cotizacion();
        $payload = ['motivo_no_vigente' => 'finalizacion_adm', 'observacion_no_vigente' => 'Cierre contable'];

        $this->moverANoVigente($cot, $payload)
            ->assertStatus(403)
            ->assertJsonPath('code', 'SUPERADMIN_ONLY');
        $this->assertSame('Cotización Enviada', $cot->fresh()->estado->nombre);

        $this->actingAsUser(['role' => 'superadmin']);
        $this->moverANoVigente($cot, $payload)->assertOk();
        $this->assertSame('finalizacion_adm', $cot->fresh()->motivo_no_vigente);
    }

    public function test_solo_superadmin_reactiva_una_finalizacion_administrativa(): void
    {
        $cot = $this->cotizacion(NoVigente::ESTADO, [
            'motivo_no_vigente' => 'finalizacion_adm', 'observacion_no_vigente' => 'Cierre contable',
        ]);
        $enviada = Estado::firstOrCreate(['scope' => 'cotizacion', 'nombre' => 'Cotización Enviada'], ['orden' => 1]);

        $this->patchJson("/api/cotizaciones/{$cot->id}/estado", ['estado_id' => $enviada->id])
            ->assertStatus(403)
            ->assertJsonPath('code', 'SUPERADMIN_ONLY');

        $this->actingAsUser(['role' => 'superadmin']);
        $this->patchJson("/api/cotizaciones/{$cot->id}/estado", ['estado_id' => $enviada->id])->assertOk();
    }

    public function test_finalizada_solo_desde_ventas(): void
    {
        $payload = ['motivo_no_vigente' => 'finalizada', 'observacion_no_vigente' => 'Entregado y pagado'];

        $enviada = $this->cotizacion();
        $this->moverANoVigente($enviada, $payload)
            ->assertStatus(422)
            ->assertJsonPath('code', 'NOT_SOLD');

        $venta = $this->cotizacion('Ventas', ['fecha_venta' => '2026-10-01']);
        $this->moverANoVigente($venta, $payload)->assertOk();
        $this->assertSame('finalizada', $venta->fresh()->motivo_no_vigente);
    }

    public function test_volver_al_flujo_limpia_el_motivo_y_lo_deja_en_el_historial(): void
    {
        $cot = $this->cotizacion();
        $this->moverANoVigente($cot, [
            'motivo_no_vigente' => 'desistida', 'observacion_no_vigente' => 'Sin presupuesto',
        ])->assertOk();

        $enviada = Estado::where('nombre', 'Cotización Enviada')->first();
        $this->patchJson("/api/cotizaciones/{$cot->id}/estado", ['estado_id' => $enviada->id])->assertOk();

        $cot->refresh();
        $this->assertSame($enviada->id, $cot->estado_id);
        $this->assertNull($cot->motivo_no_vigente);
        $this->assertNull($cot->observacion_no_vigente);
        $this->assertNull($cot->fecha_no_vigente);
        $this->assertNull($cot->no_vigente_por_id);
        $this->assertDatabaseHas('historial_etapas', [
            'model_id' => $cot->id, 'observaciones' => 'Desistida: Sin presupuesto',
        ]);
    }

    public function test_no_vigente_sale_de_vencimientos_y_entregas_de_la_agenda(): void
    {
        $this->travelTo('2026-10-06');
        $venta = $this->cotizacion('Ventas', ['fecha_venta' => '2026-10-06', 'plazo_produccion' => '20 dias']);
        $this->assertCount(1, $this->getJson('/api/eventos?start=2026-10-01&end=2026-11-01')->assertOk()->json());

        $this->moverANoVigente($venta, [
            'motivo_no_vigente' => 'finalizada', 'observacion_no_vigente' => 'Entregado',
        ])->assertOk();

        $this->assertSame([], $this->getJson('/api/eventos?start=2026-10-01&end=2026-11-01')->assertOk()->json());
    }
}
