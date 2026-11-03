<?php

namespace Tests\Feature\Comercial;

use App\Models\User;
use Tests\TestCase;

class EjecutivoEndpointTest extends TestCase
{
    public function test_requiere_autenticacion(): void
    {
        $this->getJson('/api/ejecutivos')->assertUnauthorized();
        $this->getJson('/api/usuarios/ejecutivos')->assertUnauthorized();
    }

    public function test_lista_solo_users_con_role_ejecutivo_o_superadmin(): void
    {
        $this->actingAsAdmin(); // 'admin' NO debería aparecer
        User::factory()->create(['name' => 'Ana Ejecutiva', 'role' => 'ejecutivo']);
        User::factory()->create(['name' => 'Beto Super', 'role' => 'superadmin']);
        User::factory()->create(['name' => 'Carlos Otro', 'role' => 'fabrica']);

        $response = $this->getJson('/api/ejecutivos')->assertOk();

        $nombres = array_column($response->json(), 'nombre');
        $this->assertContains('Ana Ejecutiva', $nombres);
        $this->assertContains('Beto Super', $nombres);
        $this->assertNotContains('Carlos Otro', $nombres);
    }

    public function test_response_tiene_keys_remapeadas_id_nombre_email_role(): void
    {
        $this->actingAsAdmin();
        User::factory()->create([
            'name' => 'Juan Pérez',
            'email' => 'juan@bomberos.cl',
            'role' => 'ejecutivo',
        ]);

        $response = $this->getJson('/api/ejecutivos')->assertOk();

        $found = collect($response->json())->firstWhere('email', 'juan@bomberos.cl');
        $this->assertNotNull($found);
        $this->assertSame('Juan Pérez', $found['nombre']);
        $this->assertSame('ejecutivo', $found['role']);
        $this->assertArrayHasKey('id', $found);
        $this->assertArrayNotHasKey('password', $found);
        $this->assertArrayNotHasKey('name', $found); // ya remapeada a 'nombre'
    }

    public function test_alias_usuarios_ejecutivos_funciona_igual(): void
    {
        $this->actingAsAdmin();
        User::factory()->create(['role' => 'ejecutivo']);

        $r1 = $this->getJson('/api/ejecutivos')->assertOk()->json();
        $r2 = $this->getJson('/api/usuarios/ejecutivos')->assertOk()->json();

        $this->assertSame($r1, $r2);
    }
}
