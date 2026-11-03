<?php

namespace Tests\Feature\Comercial;

use App\Models\Cliente;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CorregirUbicacionClientesTest extends TestCase
{
    private const NUBLE = 16;
    private const BIOBIO = 8;
    private const DIGUILLIN = 53;
    private const ITATA = 54;

    private int $yungay;
    private int $comunaSinProvincia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->yungay = $this->comuna('Yungay', self::NUBLE, self::DIGUILLIN);
        $this->comunaSinProvincia = $this->comuna('Comuna sin provincia', self::NUBLE, null);
    }

    private function comuna(string $nombre, int $region, ?int $provincia): int
    {
        return DB::table('comunas')->insertGetId([
            'nombre' => $nombre, 'region_id' => $region, 'provincia_id' => $provincia,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function cliente(array $attrs): Cliente
    {
        return Cliente::factory()->create($attrs);
    }

    private function ubicacion(Cliente $c): array
    {
        $f = $c->fresh();

        return [$f->region_id, $f->provincia_id, $f->comuna_id];
    }

    public function test_simulacro_informa_pero_no_guarda(): void
    {
        $c = $this->cliente(['nombre_empresa' => 'Tienda Ñuble', 'region_id' => self::NUBLE, 'provincia_id' => self::ITATA, 'comuna_id' => $this->yungay]);

        $this->artisan('clientes:corregir-ubicacion')
            ->expectsOutputToContain('Con ubicación incoherente: 1')
            ->expectsOutputToContain('Tienda Ñuble')
            ->expectsOutputToContain('Simulacro: no se guardó nada')
            ->assertExitCode(0);

        $this->assertSame([self::NUBLE, self::ITATA, $this->yungay], $this->ubicacion($c));
    }

    public function test_apply_corrige_la_provincia_y_deja_comuna_y_fecha_intactas(): void
    {
        $c = $this->cliente(['region_id' => self::NUBLE, 'provincia_id' => self::ITATA, 'comuna_id' => $this->yungay]);
        DB::table('clientes')->where('id', $c->id)->update(['updated_at' => '2026-10-06 22:33:32']);

        $this->artisan('clientes:corregir-ubicacion', ['--apply' => true])
            ->expectsOutputToContain('Corregidos: 1 de 1')
            ->assertExitCode(0);

        $this->assertSame([self::NUBLE, self::DIGUILLIN, $this->yungay], $this->ubicacion($c));
        $this->assertSame('2026-10-06 22:33:32', (string) DB::table('clientes')->where('id', $c->id)->value('updated_at'));
    }

    public function test_es_idempotente(): void
    {
        $this->cliente(['region_id' => self::NUBLE, 'provincia_id' => self::ITATA, 'comuna_id' => $this->yungay]);

        $this->artisan('clientes:corregir-ubicacion', ['--apply' => true])->assertExitCode(0);

        $this->artisan('clientes:corregir-ubicacion', ['--apply' => true])
            ->expectsOutputToContain('Nada que corregir')
            ->assertExitCode(0);
    }

    public function test_region_de_otra_zona_se_corrige_junto_con_la_provincia(): void
    {
        $c = $this->cliente(['region_id' => self::BIOBIO, 'provincia_id' => 81, 'comuna_id' => $this->yungay]);

        $this->artisan('clientes:corregir-ubicacion', ['--apply' => true])->assertExitCode(0);

        $this->assertSame([self::NUBLE, self::DIGUILLIN, $this->yungay], $this->ubicacion($c));
    }

    public function test_comuna_sin_provincia_no_toca_la_provincia_si_la_region_coincide(): void
    {
        $c = $this->cliente(['region_id' => self::NUBLE, 'provincia_id' => self::ITATA, 'comuna_id' => $this->comunaSinProvincia]);

        $this->artisan('clientes:corregir-ubicacion', ['--apply' => true])
            ->expectsOutputToContain('Nada que corregir')
            ->assertExitCode(0);

        $this->assertSame([self::NUBLE, self::ITATA, $this->comunaSinProvincia], $this->ubicacion($c));
    }

    public function test_vacios_solo_se_completan_con_la_opcion(): void
    {
        $c = $this->cliente(['region_id' => null, 'provincia_id' => null, 'comuna_id' => $this->yungay]);

        $this->artisan('clientes:corregir-ubicacion', ['--apply' => true])->expectsOutputToContain('Nada que corregir')->assertExitCode(0);
        $this->assertSame([null, null, $this->yungay], $this->ubicacion($c));

        $this->artisan('clientes:corregir-ubicacion', ['--apply' => true, '--incluir-vacios' => true])->assertExitCode(0);
        $this->assertSame([self::NUBLE, self::DIGUILLIN, $this->yungay], $this->ubicacion($c));
    }

    public function test_clientes_sin_comuna_no_se_tocan(): void
    {
        $c = $this->cliente(['region_id' => self::BIOBIO, 'provincia_id' => self::ITATA, 'comuna_id' => null]);

        $this->artisan('clientes:corregir-ubicacion', ['--apply' => true, '--incluir-vacios' => true])
            ->expectsOutputToContain('Nada que corregir')
            ->assertExitCode(0);

        $this->assertSame([self::BIOBIO, self::ITATA, null], $this->ubicacion($c));
    }
}
