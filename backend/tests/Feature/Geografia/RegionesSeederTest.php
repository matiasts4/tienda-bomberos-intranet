<?php

namespace Tests\Feature\Geografia;

use Database\Seeders\ComunasSeeder;
use Database\Seeders\RegionesSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegionesSeederTest extends TestCase
{
    public function test_carga_las_16_regiones_con_su_numeracion_oficial(): void
    {
        $this->seed(RegionesSeeder::class);

        $this->assertSame(range(1, 16), DB::table('regiones')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertStringContainsString('Metropolitana', DB::table('regiones')->where('id', 13)->value('nombre'));
        $this->assertStringContainsString('Arica', DB::table('regiones')->where('id', 15)->value('nombre'));
        $this->assertStringContainsString('Ñuble', DB::table('regiones')->where('id', 16)->value('nombre'));
    }

    public function test_cada_region_queda_asociada_a_una_zona_existente(): void
    {
        $this->seed(RegionesSeeder::class);

        $huerfanas = DB::table('regiones')
            ->leftJoin('zonas', 'zonas.id', '=', 'regiones.zona_id')
            ->whereNull('zonas.id')
            ->count();

        $this->assertSame(0, $huerfanas);
    }

    public function test_es_idempotente(): void
    {
        $this->seed(RegionesSeeder::class);
        $this->seed(RegionesSeeder::class);

        $this->assertSame(16, DB::table('regiones')->count());
    }

    public function test_no_pisa_regiones_que_ya_existen(): void
    {
        DB::table('regiones')->insert(['id' => 5, 'nombre' => 'Nombre cargado desde la Red de Bomberos', 'zona_id' => null]);

        $this->seed(RegionesSeeder::class);

        $this->assertSame('Nombre cargado desde la Red de Bomberos', DB::table('regiones')->where('id', 5)->value('nombre'));
    }

    public function test_con_regiones_cargadas_el_catalogo_de_comunas_ya_no_se_omite(): void
    {
        $this->seed(RegionesSeeder::class);
        $this->seed(ComunasSeeder::class);

        $this->assertGreaterThan(300, DB::table('comunas')->count());
    }
}
