<?php

namespace Tests\Feature\Comercial;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CotizacionOptionsTest extends TestCase
{
    public function test_endpoint_es_publico_sin_auth(): void
    {
        $this->getJson('/api/cotizaciones/options')->assertOk();
    }

    public function test_devuelve_estructura_completa(): void
    {
        $this->getJson('/api/cotizaciones/options')
            ->assertOk()
            ->assertJsonStructure([
                'colores',
                'tallas',
                'generos',
                'productos',
                'personalizaciones',
                'plazos_produccion',
                'condiciones_pago',
                'despacho',
            ]);
    }

    public function test_listas_hardcoded_contienen_valores_esperados(): void
    {
        $response = $this->getJson('/api/cotizaciones/options')->assertOk();
        $body = $response->json();

        // Productos: lista hardcoded del controller
        $this->assertContains('Cargo', $body['productos']);
        $this->assertContains('Polera Polo MC', $body['productos']);
        $this->assertContains('KIT CUARTEL', $body['productos']);
        $this->assertContains('Quepi', $body['productos']);

        // Personalizaciones
        $this->assertSame(
            ['Bordado', 'Estampado', 'Bordado y Estampado', 'Sin Personalización', 'Generico'],
            $body['personalizaciones']
        );

        // Plazos
        $this->assertContains('20 dias', $body['plazos_produccion']);

        // Condiciones de pago
        $this->assertContains('Contado - Transferencia', $body['condiciones_pago']);

        // Despacho
        $this->assertSame(['Incluido', 'Por pagar'], $body['despacho']);
    }

    public function test_filtra_solo_catalogos_pte_activos(): void
    {
        // Insertamos directamente para evitar factories de PTE
        DB::table('pte_colores')->insert([
            ['nombre' => 'Rojo', 'is_activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Verde', 'is_activo' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('pte_tallas')->insert([
            ['nombre' => 'M', 'orden' => 2, 'is_activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'S', 'orden' => 1, 'is_activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'XXX', 'orden' => 99, 'is_activo' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('pte_generos')->insert([
            ['nombre' => 'M', 'is_activo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $body = $this->getJson('/api/cotizaciones/options')->assertOk()->json();

        $this->assertSame(['Rojo'], array_values($body['colores']));

        // Tallas: filtra inactivas y respeta orden
        $this->assertSame(['S', 'M'], array_values($body['tallas']));

        $this->assertSame(['M'], array_values($body['generos']));
    }
}
