<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    /**
     * Valida que el inicio de sesion con credenciales validas retorne un token Sanctum.
     */
    public function test_login_successful_with_valid_credentials_returns_token(): void
    {
        $user = User::factory()->create([
            'email' => 'operador@bomberos.cl',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'operador@bomberos.cl',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
        ]);
        $this->assertNotEmpty($response->json('token'));
    }

    /**
     * Valida que el inicio de sesion rechace credenciales incorrectas con codigo 422.
     */
    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'operador@bomberos.cl',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'operador@bomberos.cl',
            'password' => 'clave-incorrecta',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Comprueba que el endpoint /api/me retorne el perfil del usuario autenticado con su rol y permisos de modulos.
     */
    public function test_me_returns_authenticated_user_profile_with_role(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Administrador',
            'email' => 'admin@bomberos.cl',
            'telefono' => '+56912345678',
            'role' => 'admin',
            'modulos' => ['comercial', 'inventario'],
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/me');

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $user->id,
            'name' => 'Usuario Administrador',
            'email' => 'admin@bomberos.cl',
            'telefono' => '+56912345678',
            'role' => 'admin',
            'modulos' => ['comercial', 'inventario'],
        ]);
    }

    /**
     * Comprueba que las solicitudes sin autenticacion a /api/me sean rechazadas con 401.
     */
    public function test_unauthenticated_request_to_me_returns_unauthorized(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }

    /**
     * Valida que el endpoint de logout revoque los tokens activos del usuario.
     */
    public function test_logout_revokes_tokens(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('app-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout');

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
        $this->assertCount(0, $user->fresh()->tokens);
    }
}
