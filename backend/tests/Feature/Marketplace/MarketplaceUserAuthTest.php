<?php

namespace Tests\Feature\Marketplace;

use Tests\TestCase;
use App\Models\Marketplace;
use App\Models\MktUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class MarketplaceUserAuthTest extends TestCase
{
    public function test_admin_crea_usuario_con_clave_por_defecto_rut()
    {
        $admin = User::first() ?: User::factory()->create();
        $slug = 'san-clemente-' . uniqid();
        $marketplace = Marketplace::create([
            'name' => 'San Clemente',
            'slug' => $slug,
            'password' => Hash::make('secret123'),
            'dealer_email' => 'dealer@test.com',
            'active' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/admin/marketplace/marketplaces/{$marketplace->id}/users", [
            'name' => 'Juan Pérez Bombero',
            'rut' => '21.669.586-0',
            'email' => 'juan.perez@bomberos.cl',
            'phone' => '+56987654321',
            'company_unit' => '1ª Compañía San Clemente',
            'cargo' => 'Voluntario',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('mkt_users', [
            'marketplace_id' => $marketplace->id,
            'name' => 'Juan Pérez Bombero',
            'rut' => '21.669.586-0',
        ]);

        $user = MktUser::where('marketplace_id', $marketplace->id)->first();
        // Clave por defecto es el RUT limpio
        $this->assertTrue(Hash::check('216695860', $user->password));
    }

    public function test_login_cliente_con_rut_y_password()
    {
        $slug = 'san-clemente-' . uniqid();
        $marketplace = Marketplace::create([
            'name' => 'San Clemente',
            'slug' => $slug,
            'password' => Hash::make('secret123'),
            'dealer_email' => 'dealer@test.com',
            'active' => true,
        ]);

        $user = MktUser::create([
            'marketplace_id' => $marketplace->id,
            'name' => 'Claudio Santibáñez',
            'rut' => '21.669.586-0',
            'email' => 'claudio@bomberos.cl',
            'phone' => '+56912345678',
            'company_unit' => '2ª Compañía San Clemente',
            'cargo' => 'Teniente',
            'password' => Hash::make('216695860'),
            'active' => true,
        ]);

        // Login con RUT formateado y contraseña limpia
        $response = $this->postJson('/api/marketplace/login', [
            'slug' => $slug,
            'rut' => '21.669.586-0',
            'password' => '216695860',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'token',
            'marketplace' => ['id', 'name', 'slug'],
            'user' => ['id', 'name', 'rut', 'email', 'phone', 'company_unit', 'cargo'],
        ]);
        $this->assertEquals('Claudio Santibáñez', $response->json('user.name'));

        // Probar /api/marketplace/me con el token
        $token = $response->json('token');
        $meResponse = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/marketplace/me');
        $meResponse->assertStatus(200);
        $this->assertEquals('Claudio Santibáñez', $meResponse->json('user.name'));
    }

    public function test_rechazo_login_si_usuario_inactivo_o_rut_inexistente()
    {
        $slug = 'san-clemente-' . uniqid();
        $marketplace = Marketplace::create([
            'name' => 'San Clemente',
            'slug' => $slug,
            'password' => Hash::make('secret123'),
            'dealer_email' => 'dealer@test.com',
            'active' => true,
        ]);

        $user = MktUser::create([
            'marketplace_id' => $marketplace->id,
            'name' => 'Usuario Inactivo',
            'rut' => '11.111.111-1',
            'password' => Hash::make('111111111'),
            'active' => false,
        ]);

        $response = $this->postJson('/api/marketplace/login', [
            'slug' => $slug,
            'rut' => '11.111.111-1',
            'password' => '111111111',
        ]);

        $response->assertStatus(401);
    }
}
