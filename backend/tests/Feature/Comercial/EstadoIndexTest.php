<?php

namespace Tests\Feature\Comercial;

use Tests\TestCase;

class EstadoIndexTest extends TestCase
{
    public function test_lista_todos_los_estados_sin_filtro(): void
    {
        $this->actingAsAdmin();
        $this->seedEstadosCotizacion();   // 4 (+ "No Vigente", que crea la migración)
        $this->seedEstadosOportunidad();  // 5

        $this->getJson('/api/estados')
            ->assertOk()
            ->assertJsonCount(10);
    }

    public function test_filtra_por_scope(): void
    {
        $this->actingAsAdmin();
        $this->seedEstadosCotizacion();
        $this->seedEstadosOportunidad();

        $response = $this->getJson('/api/estados?scope=cotizacion')->assertOk();

        $this->assertCount(5, $response->json());
        foreach ($response->json() as $row) {
            $this->assertSame('cotizacion', $row['scope']);
        }
    }
}
