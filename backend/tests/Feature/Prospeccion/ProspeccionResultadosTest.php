<?php

namespace Tests\Feature\Prospeccion;

use App\Models\Cargo;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\Cuerpo;
use App\Models\Estado;
use App\Models\Oportunidad;
use App\Models\ProspeccionContacto;
use App\Models\ProspeccionPausa;
use App\Support\ColaProspeccion;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Resultados de llamada como los vive un ejecutivo: cadencia de intentos,
 * seguimiento con hora, "no interesado" con fecha de recontacto y pausa de la
 * cuenta, derivación al decisor, datos malos y calificación estructurada.
 */
class ProspeccionResultadosTest extends TestCase
{
    private Cuerpo $cuerpo;
    private Compania $compania;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-21 10:00'));
        $this->actingAsAdmin();

        $this->cuerpo = Cuerpo::create(['id' => '0500100', 'nombre' => 'Valparaíso']);
        $this->compania = Compania::create(['id' => '0500101', 'cuerpo_id' => $this->cuerpo->id, 'nombre' => '1ª Compañía', 'numero' => 1]);
    }

    /** Contacto de la 1ª Compañía, creado "antes" de cualquier gestión. */
    private function contacto(string $nombre): Contacto
    {
        $contacto = Contacto::factory()->create(['nombre' => $nombre, 'telefono' => '+56911111111']);
        $contacto->cuerpos()->attach($this->cuerpo->id);
        $contacto->companias()->attach($this->compania->id);
        Contacto::whereKey($contacto->id)->update(['updated_at' => now()->subDays(30)]);

        return $contacto->fresh();
    }

    private function gestionar(Contacto $contacto, array $datos, int $estado = 201)
    {
        return $this->postJson("/api/prospeccion/contactos/{$contacto->id}/interaccion", $datos)->assertStatus($estado);
    }

    /** @return array<string, int> nombre => prioridad */
    private function cola(string $query = ''): array
    {
        $filas = $this->getJson('/api/prospeccion/contactos?' . $query)->assertOk()->json('data');

        return array_column($filas, 'prioridad', 'nombre');
    }

    private function conteos(): array
    {
        return $this->getJson('/api/prospeccion/contactos/counts')->assertOk()->json();
    }

    // ── cadencia ───────────────────────────────────────────────────────────

    public function test_tras_cinco_intentos_sin_respuesta_el_contacto_queda_inalcanzable(): void
    {
        $contacto = $this->contacto('Sin Respuesta');

        foreach (range(1, ColaProspeccion::MAX_INTENTOS) as $n) {
            $this->gestionar($contacto, ['tipo' => $n % 2 ? 'no_contesta' : 'buzon'])
                ->assertJsonPath('interaccion.intento', $n);
        }

        $this->assertArrayNotHasKey('Sin Respuesta', $this->cola());
        $this->assertArrayHasKey('Sin Respuesta', $this->cola('filtro=inalcanzables'));
        $this->assertSame(1, $this->conteos()['inalcanzables']);

        // Corregir sus datos lo devuelve a la cola.
        $this->travel(1)->minutes();
        $this->patchJson("/api/contactos/{$contacto->id}", ['telefono' => '+56922222222'])->assertOk();
        $this->assertArrayHasKey('Sin Respuesta', $this->cola());
    }

    public function test_una_conversacion_reinicia_la_cuenta_de_intentos(): void
    {
        $contacto = $this->contacto('Contestó Una Vez');

        $this->gestionar($contacto, ['tipo' => 'no_contesta']);
        $this->gestionar($contacto, ['tipo' => 'no_contesta'])->assertJsonPath('interaccion.intento', 2);
        $this->gestionar($contacto, ['tipo' => 'agendar_seguimiento', 'proxima_llamada' => '2026-10-21'])
            ->assertJsonPath('interaccion.intento', null);
        $this->gestionar($contacto, ['tipo' => 'no_contesta'])->assertJsonPath('interaccion.intento', 1);
    }

    // ── seguimiento acordado ───────────────────────────────────────────────

    public function test_seguimiento_acordado_exige_fecha_y_queda_para_hoy_con_su_hora(): void
    {
        $contacto = $this->contacto('Llamar En La Tarde');

        $this->gestionar($contacto, ['tipo' => 'agendar_seguimiento'], 422)->assertJsonValidationErrors(['proxima_llamada']);

        $this->gestionar($contacto, [
            'tipo' => 'agendar_seguimiento',
            'proxima_llamada' => '2026-10-21',
            'hora' => '15:30',
            'objetivo' => 'Enviar cotización',
        ]);

        $fila = $this->getJson('/api/prospeccion/contactos?filtro=hoy')->assertOk()->json('data.0');
        $this->assertSame('Llamar En La Tarde', $fila['nombre']);
        $this->assertSame(2, $fila['prioridad']);
        $this->assertStringStartsWith('15:30', $fila['proxima_hora']);
        $this->assertSame('Enviar cotización', $fila['proximo_objetivo']);
    }

    // ── no interesado ──────────────────────────────────────────────────────

    public function test_no_interesado_vuelve_en_su_fecha_y_pausa_a_toda_la_compania(): void
    {
        $capitan = $this->contacto('Capitán');
        $director = $this->contacto('Director');

        $respuesta = $this->gestionar($capitan, [
            'tipo' => 'no_califica',
            'motivo' => 'ya_tiene_proveedor',
            'proxima_llamada' => '2027-01-19',
            'pausar_compania' => true,
        ]);
        $this->assertSame('2027-01-18', Carbon::parse($respuesta->json('pausa.hasta'))->toDateString());

        // Ni el capitán ni el resto de la compañía se llaman mientras dura la pausa.
        $this->assertSame([], $this->cola());
        $this->assertSame(['Director' => 4], $this->cola('filtro=bloqueados'));
        $this->assertSame(1, $this->conteos()['bloqueados']);

        // El día del recontacto vuelven los dos: el capitán como "para hoy".
        $this->travelTo(Carbon::parse('2027-01-19 09:00'));
        $this->assertSame(['Capitán' => 2, 'Director' => 4], $this->cola());
    }

    public function test_levantar_la_pausa_reactiva_la_compania(): void
    {
        $capitan = $this->contacto('Capitán');
        $this->contacto('Director');

        $pausaId = $this->gestionar($capitan, [
            'tipo' => 'no_califica', 'motivo' => 'sin_presupuesto', 'proxima_llamada' => '2027-03-01', 'pausar_compania' => true,
        ])->json('pausa.id');

        $this->deleteJson("/api/prospeccion/pausas/{$pausaId}")->assertOk();

        $this->assertSame(['Director' => 4], $this->cola());
        $this->assertNotNull(ProspeccionPausa::find($pausaId)->levantada_at);
    }

    public function test_pausar_la_compania_exige_fecha_de_recontacto(): void
    {
        $contacto = $this->contacto('Sin Fecha');

        $this->gestionar($contacto, ['tipo' => 'no_califica', 'motivo' => 'no_le_interesa', 'pausar_compania' => true], 422)
            ->assertJsonValidationErrors(['proxima_llamada']);
    }

    public function test_no_interesado_sin_motivo_se_guarda_como_otro(): void
    {
        // Así lo envía la versión anterior del frontend durante el despliegue.
        $contacto = $this->contacto('Frontend Anterior');

        $this->gestionar($contacto, ['tipo' => 'no_califica', 'notas' => 'No quiere'])
            ->assertJsonPath('interaccion.motivo', 'otro');
        $this->assertArrayNotHasKey('Frontend Anterior', $this->cola());
    }

    // ── cuentas con lead abierto ───────────────────────────────────────────

    public function test_una_compania_con_lead_abierto_no_se_vuelve_a_llamar(): void
    {
        $this->contacto('Teniente');
        $abierto = Estado::create(['nombre' => 'Cotización enviada', 'scope' => 'oportunidad', 'orden' => 1]);
        $cerrado = Estado::create(['nombre' => 'Cerrada perdida', 'scope' => 'oportunidad', 'orden' => 2]);

        $lead = Oportunidad::create(['estado_id' => $abierto->id, 'compania_id' => $this->compania->id, 'fecha_ingreso' => '2026-10-01']);
        $this->assertSame([], $this->cola());
        $this->assertSame(['Teniente' => 4], $this->cola('filtro=bloqueados'));

        // Cerrado el lead, la compañía vuelve a la cola.
        $lead->update(['estado_id' => $cerrado->id]);
        $this->assertSame(['Teniente' => 4], $this->cola());
    }

    // ── derivación ─────────────────────────────────────────────────────────

    public function test_derivar_a_un_contacto_nuevo_lo_crea_en_la_misma_cuenta_y_lo_agenda(): void
    {
        $bombero = $this->contacto('Bombero');
        $capitan = Cargo::create(['nombre' => 'CAPITAN']);

        $respuesta = $this->postJson("/api/prospeccion/contactos/{$bombero->id}/derivar", [
            'nuevo' => ['nombre' => 'Capitán Nuevo', 'cargo_id' => $capitan->id, 'telefono' => '+56933333333'],
            'proxima_llamada' => '2026-10-21',
            'hora' => '11:00',
            'notas' => 'El capitán ve las compras',
        ])->assertCreated();

        $nuevo = Contacto::findOrFail($respuesta->json('destino.id'));
        $this->assertSame('CAPITAN', $nuevo->cargo);
        $this->assertSame([$this->compania->id], $nuevo->companias->pluck('id')->all());
        $this->assertSame([$this->cuerpo->id], $nuevo->cuerpos->pluck('id')->all());

        $this->assertSame(['Capitán Nuevo' => 2], $this->cola());
        $this->assertSame(['Bombero' => 99], $this->cola('filtro=derivado'));

        $fila = $this->getJson('/api/prospeccion/contactos?filtro=hoy')->json('data.0');
        $this->assertSame('Derivado por Bombero', $fila['proximo_objetivo']);
        $this->assertSame($nuevo->id, ProspeccionContacto::where('contacto_id', $bombero->id)->value('derivado_a_id'));
    }

    public function test_derivar_a_un_contacto_existente_le_agenda_el_seguimiento(): void
    {
        $bombero = $this->contacto('Bombero');
        $tesorero = $this->contacto('Tesorero');

        $this->postJson("/api/prospeccion/contactos/{$bombero->id}/derivar", [
            'derivado_a_id' => $tesorero->id,
            'proxima_llamada' => '2026-10-23',
        ])->assertCreated()->assertJsonPath('destino.id', $tesorero->id);

        $this->assertSame(['Tesorero' => 5], $this->cola());
    }

    public function test_no_se_puede_derivar_a_si_mismo(): void
    {
        $bombero = $this->contacto('Bombero');

        $this->postJson("/api/prospeccion/contactos/{$bombero->id}/derivar", [
            'derivado_a_id' => $bombero->id,
            'proxima_llamada' => '2026-10-21',
        ])->assertStatus(422)->assertJsonValidationErrors(['derivado_a_id']);
    }

    // ── datos malos ────────────────────────────────────────────────────────

    public function test_numero_equivocado_sale_de_la_cola_hasta_corregir_el_contacto(): void
    {
        $contacto = $this->contacto('Número Malo');

        $this->gestionar($contacto, ['tipo' => 'numero_equivocado']);
        $this->assertSame([], $this->cola());
        $this->assertSame(1, $this->conteos()['numero_equivocado']);

        $this->travel(1)->minutes();
        $this->patchJson("/api/contactos/{$contacto->id}", ['telefono' => '+56944444444'])->assertOk();
        $this->assertSame(['Número Malo' => 3], $this->cola());
    }

    public function test_calificar_y_derivar_no_se_registran_como_gestion_suelta(): void
    {
        $contacto = $this->contacto('Atajo');

        $this->gestionar($contacto, ['tipo' => 'calificado'], 422)->assertJsonValidationErrors(['tipo']);
        $this->gestionar($contacto, ['tipo' => 'derivado'], 422)->assertJsonValidationErrors(['tipo']);
    }

    // ── calificación ───────────────────────────────────────────────────────

    public function test_calificar_con_datos_estructurados_arma_el_resumen_de_la_oportunidad(): void
    {
        $this->seedEstadosOportunidad();
        Estado::create(['nombre' => 'Nuevo', 'scope' => 'oportunidad', 'orden' => 0]);
        $contacto = $this->contacto('Interesado');
        $user = auth()->user();

        $id = $this->postJson("/api/prospeccion/contactos/{$contacto->id}/calificar", [
            'necesidad' => '20 chaquetas estructurales',
            'cantidad' => 20,
            'plazo' => '1_3_meses',
            'presupuesto' => 'en_tramite',
            'decisor' => 'Directorio de la compañía',
            'user_id' => $user->id,
        ])->assertCreated()->json('oportunidad_id');

        $mensaje = Oportunidad::findOrFail($id)->mensaje_prospeccion;
        $this->assertStringContainsString('Necesita: 20 chaquetas estructurales', $mensaje);
        $this->assertStringContainsString('Plazo: En 1 a 3 meses', $mensaje);
        $this->assertStringContainsString('Presupuesto: En trámite', $mensaje);

        $gestion = ProspeccionContacto::where('contacto_id', $contacto->id)->latest('id')->first();
        $this->assertSame(20, $gestion->datos['cantidad']);
    }
}
