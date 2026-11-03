<?php

namespace Tests\Feature\Comercial;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Estado;
use App\Models\Oportunidad;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgendaVencimientosTest extends TestCase
{
    /** @var array<string, Estado> */
    private array $estados = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-29 10:00:00')); // martes

        foreach (['Cotización Enviada', 'Ventas', 'Rechazado', 'Anulado'] as $i => $nombre) {
            $this->estados[$nombre] = Estado::create(['nombre' => $nombre, 'scope' => 'cotizacion', 'orden' => $i + 1]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function cotizacion(User $owner, string $estado, ?string $vence, array $attrs = []): Cotizacion
    {
        return Cotizacion::factory()->forUser($owner)->withEstado($this->estados[$estado])->create(array_merge([
            'numero' => null,
            'fecha_vencimiento' => $vence,
        ], $attrs));
    }

    private function agenda(array $query = [])
    {
        return $this->getJson('/api/eventos?' . http_build_query(array_merge([
            'start' => '2026-09-01', 'end' => '2026-10-31',
        ], $query)))->assertOk();
    }

    private function vencimientos($response): array
    {
        return collect($response->json())
            ->where('extendedProps.tipo', 'vencimiento_automatico')
            ->keyBy('extendedProps.cotizacion_id')
            ->all();
    }

    public function test_ejecutivo_no_puede_ver_la_agenda_de_otro_con_ejecutivo_id(): void
    {
        $otro = User::factory()->create(['role' => 'ejecutivo']);
        $ajena = $this->cotizacion($otro, 'Cotización Enviada', '2026-10-02');
        $yo = $this->actingAsEjecutivo();
        $propia = $this->cotizacion($yo, 'Cotización Enviada', '2026-10-02');

        $ids = array_keys($this->vencimientos($this->agenda(['ejecutivo_id' => $otro->id])));

        $this->assertSame([$propia->id], $ids);
        $this->assertNotContains($ajena->id, $ids);
    }

    public function test_superadmin_si_puede_ver_cotizaciones_de_todos(): void
    {
        $otro = User::factory()->create(['role' => 'ejecutivo']);
        $ajena = $this->cotizacion($otro, 'Cotización Enviada', '2026-10-02');
        $this->actingAsUser(['role' => 'superadmin']);

        $this->assertArrayHasKey($ajena->id, $this->vencimientos($this->agenda(['ejecutivo_id' => $otro->id])));
    }

    public function test_solo_aparecen_vencimientos_de_cotizaciones_enviadas(): void
    {
        $yo = $this->actingAsEjecutivo();
        $enviada = $this->cotizacion($yo, 'Cotización Enviada', '2026-10-02');
        $ventas = $this->cotizacion($yo, 'Ventas', '2026-10-03');
        $rechazada = $this->cotizacion($yo, 'Rechazado', '2026-10-02');
        $anulada = $this->cotizacion($yo, 'Anulado', '2026-10-02');

        $ids = array_keys($this->vencimientos($this->agenda()));
        sort($ids);

        $this->assertSame([$enviada->id], $ids);
        $this->assertNotContains($ventas->id, $ids);
        $this->assertNotContains($rechazada->id, $ids);
        $this->assertNotContains($anulada->id, $ids);
    }

    public function test_descripcion_indica_cuanto_falta_o_cuanto_paso(): void
    {
        $yo = $this->actingAsEjecutivo();
        $casos = [
            '2026-09-29' => ['vence hoy', 0],
            '2026-09-30' => ['vence mañana', 1],
            '2026-10-02' => ['vence en 3 días', 3],
            '2026-09-28' => ['venció ayer', -1],
            '2026-09-24' => ['venció hace 5 días', -5],
        ];
        $cots = [];
        foreach ($casos as $fecha => $_) {
            $cots[$fecha] = $this->cotizacion($yo, 'Cotización Enviada', $fecha);
        }

        $vencimientos = $this->vencimientos($this->agenda());

        foreach ($casos as $fecha => [$texto, $dias]) {
            $ev = $vencimientos[$cots[$fecha]->id];
            $this->assertSame("Cotización #{$cots[$fecha]->id} {$texto}.", $ev['extendedProps']['descripcion']);
            $this->assertSame($dias, $ev['extendedProps']['dias_para_vencer']);
        }
    }

    public function test_agenda_muestra_cliente_de_cuerpo_y_compania(): void
    {
        DB::table('cuerpos')->insert(['id' => 'CB1', 'nombre' => 'Ñuñoa']);
        DB::table('companias')->insert(['id' => 'CB1-3', 'cuerpo_id' => 'CB1', 'nombre' => 'Tercera', 'numero' => 3]);
        $yo = $this->actingAsEjecutivo();
        $cot = $this->cotizacion($yo, 'Cotización Enviada', '2026-10-02', [
            'cliente_id' => null, 'cuerpo_id' => 'CB1', 'compania_id' => 'CB1-3',
        ]);

        $ev = $this->vencimientos($this->agenda())[$cot->id];

        $this->assertSame('Cuerpo de Bomberos de Ñuñoa – Tercera', $ev['extendedProps']['cliente_nombre']);
    }

    public function test_correo_usa_id_cliente_real_y_excluye_cerradas(): void
    {
        DB::table('alerta_configs')->insert(['clave' => 'alerta_vencimientos', 'valor' => json_encode(true)]);
        DB::table('cuerpos')->insert(['id' => 'CB2', 'nombre' => 'Valparaíso']);
        $yo = User::factory()->create(['role' => 'ejecutivo', 'email' => 'ejecutivo@example.test']);
        $abierta = $this->cotizacion($yo, 'Cotización Enviada', '2026-10-02', ['cliente_id' => null, 'cuerpo_id' => 'CB2']);
        $anulada = $this->cotizacion($yo, 'Anulado', '2026-10-02');

        $this->artisan('alerts:vencimientos')->assertSuccessful();

        $mensajes = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $mensajes);
        $html = $mensajes->first()->getOriginalMessage()->getHtmlBody();

        $this->assertStringContainsString("Cotización #{$abierta->id}", $html);
        $this->assertStringContainsString('Cuerpo de Bomberos de Valparaíso', $html);
        $this->assertStringNotContainsString("Cotización #{$anulada->id}<", $html);
        $this->assertStringNotContainsString('S/N', $html);
    }

    public function test_cotizacion_creada_desde_oportunidad_nace_con_vencimiento(): void
    {
        $estadosOp = $this->seedEstadosOportunidad();
        $admin = $this->actingAsAdmin();
        $cliente = Cliente::factory()->create();
        $op = Oportunidad::factory()->forCliente($cliente)->withEstado($estadosOp['Nueva'])->create(['user_id' => $admin->id]);

        $cotId = $this->patchJson("/api/oportunidades/{$op->id}/estado", [
            'estado_id' => $estadosOp['Aprobada - Cotización']->id,
        ])->assertOk()->json('cotizacion_id');

        // 29-09 (martes) + 15 días hábiles = 20-10 (martes)
        $this->assertSame('2026-10-20', Cotizacion::find($cotId)->fecha_vencimiento->toDateString());
    }

    public function test_comando_completar_vencimiento_simulacro_no_escribe(): void
    {
        $yo = User::factory()->create();
        $cot = $this->cotizacion($yo, 'Cotización Enviada', null, ['fecha_creacion' => '2026-04-01']);

        $this->artisan('cotizaciones:completar-vencimiento')
            ->expectsOutputToContain('Simulacro: 1')
            ->assertSuccessful();

        $this->assertNull($cot->fresh()->fecha_vencimiento);
    }

    public function test_comando_completar_vencimiento_apply_solo_abiertas_nulas_e_idempotente(): void
    {
        $yo = User::factory()->create();
        $enviada = $this->cotizacion($yo, 'Cotización Enviada', null, ['fecha_creacion' => '2026-04-01']);
        $ventas = $this->cotizacion($yo, 'Ventas', null, ['fecha_creacion' => '2026-09-28']);
        $anulada = $this->cotizacion($yo, 'Anulado', null, ['fecha_creacion' => '2026-04-01']);
        $conFecha = $this->cotizacion($yo, 'Cotización Enviada', '2026-12-01', ['fecha_creacion' => '2026-04-01']);

        $this->artisan('cotizaciones:completar-vencimiento', ['--apply' => true])->assertSuccessful();

        $this->assertSame('2026-04-22', $enviada->fresh()->fecha_vencimiento->toDateString());
        $this->assertSame('2026-10-19', $ventas->fresh()->fecha_vencimiento->toDateString());
        $this->assertNull($anulada->fresh()->fecha_vencimiento);
        $this->assertSame('2026-12-01', $conFecha->fresh()->fecha_vencimiento->toDateString());

        $this->artisan('cotizaciones:completar-vencimiento', ['--apply' => true])
            ->expectsOutputToContain('No hay cotizaciones abiertas sin fecha')
            ->assertSuccessful();
    }
}
