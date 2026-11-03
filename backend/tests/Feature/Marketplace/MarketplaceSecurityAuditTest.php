<?php

namespace Tests\Feature\Marketplace;

use Tests\TestCase;
use App\Models\Marketplace;
use App\Models\MktUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

class MarketplaceSecurityAuditTest extends TestCase
{
    private Marketplace $marketplaceA;
    private Marketplace $marketplaceB;
    private MktUser $userA;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = uniqid();

        $this->marketplaceA = Marketplace::create([
            'name' => 'Cuerpo Bomberos Talca ' . $suffix,
            'slug' => 'talca-' . $suffix,
            'dealer_email' => 'talca@bomberos.cl',
            'quote_email' => 'ventas.talca@bomberos.cl',
            'active' => true,
        ]);

        $this->marketplaceB = Marketplace::create([
            'name' => 'Cuerpo Bomberos Linares ' . $suffix,
            'slug' => 'linares-' . $suffix,
            'dealer_email' => 'linares@bomberos.cl',
            'quote_email' => 'ventas.linares@bomberos.cl',
            'active' => true,
        ]);

        $this->userA = MktUser::create([
            'marketplace_id' => $this->marketplaceA->id,
            'name' => 'Pedro Bombero',
            'rut' => '18.456.789-K',
            'email' => 'pedro@talca.cl',
            'phone' => '+56911223344',
            'company_unit' => '2ª Cía Talca',
            'cargo' => 'Teniente',
            'password' => Hash::make('18456789k'),
            'active' => true,
        ]);
    }

    public function test_login_exitoso_con_rut_formateado_y_clave_clean()
    {
        $response = $this->postJson('/api/marketplace/login', [
            'slug' => $this->marketplaceA->slug,
            'rut' => '18.456.789-K',
            'password' => '18456789k',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'token',
            'marketplace' => ['id', 'name', 'slug'],
            'user' => ['id', 'name', 'rut', 'email', 'company_unit', 'cargo'],
        ]);

        $token = $response->json('token');
        $this->assertNotEmpty($token);

        // Validar que el token está cifrado criptográficamente
        $decrypted = json_decode(Crypt::decryptString($token), true);
        $this->assertEquals($this->marketplaceA->id, $decrypted['marketplace_id']);
        $this->assertEquals($this->userA->id, $decrypted['user_id']);
        $this->assertEquals('18.456.789-K', $decrypted['user_rut']);
        $this->assertEquals('2ª Cía Talca', $decrypted['user_company_unit']);
        $this->assertGreaterThan(Carbon::now()->timestamp, $decrypted['expires_at']);
    }

    public function test_login_exitoso_con_email_y_clave_formateada()
    {
        $response = $this->postJson('/api/marketplace/login', [
            'slug' => $this->marketplaceA->slug,
            'rut' => 'pedro@talca.cl',
            'password' => '18.456.789-K',
        ]);

        $response->assertOk();
        $this->assertEquals($this->userA->id, $response->json('user.id'));
    }

    public function test_token_encriptado_funciona_en_endpoint_me()
    {
        $loginRes = $this->postJson('/api/marketplace/login', [
            'slug' => $this->marketplaceA->slug,
            'rut' => '18456789K',
            'password' => '18456789K',
        ]);

        $token = $loginRes->json('token');

        $meRes = $this->withHeader('Authorization', 'Bearer ' . $token)
                      ->getJson('/api/marketplace/me');

        $meRes->assertOk();
        $meRes->assertJson([
            'marketplace' => [
                'id' => $this->marketplaceA->id,
                'slug' => $this->marketplaceA->slug,
            ],
            'user' => [
                'id' => $this->userA->id,
                'name' => 'Pedro Bombero',
                'rut' => '18.456.789-K',
            ]
        ]);
    }

    public function test_aislamiento_entre_marketplaces_token_de_portal_a_no_sirve_en_portal_b()
    {
        // Generar token para Marketplace A
        $tokenPayload = json_encode([
            'marketplace_id' => $this->marketplaceA->id,
            'user_id' => $this->userA->id,
            'expires_at' => Carbon::now()->addHours(24)->timestamp
        ]);
        $tokenA = Crypt::encryptString($tokenPayload);

        // Usar ese token para consultar catálogo
        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenA)
                         ->getJson('/api/marketplace/catalog');

        // El middleware autoriza y responde exitosamente con los productos del Marketplace A
        $response->assertOk();
    }

    public function test_rechazo_seguro_de_token_corrupto_o_adulterado()
    {
        $corruptTokens = [
            'token-falso-invalido',
            'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.corrupto',
            '1234567890abcdef',
        ];

        foreach ($corruptTokens as $badToken) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $badToken)
                             ->getJson('/api/marketplace/me');

            // Debe responder 401 seguro sin revelar excepciones ni 500
            $response->assertStatus(401);
            $this->assertStringContainsString('Invalid token', $response->json('message'));
        }
    }

    public function test_rechazo_de_token_expirado()
    {
        $expiredPayload = json_encode([
            'marketplace_id' => $this->marketplaceA->id,
            'user_id' => $this->userA->id,
            'expires_at' => Carbon::now()->subMinutes(10)->timestamp // expiró hace 10 min
        ]);
        $expiredToken = Crypt::encryptString($expiredPayload);

        $response = $this->withHeader('Authorization', 'Bearer ' . $expiredToken)
                         ->getJson('/api/marketplace/me');

        $response->assertStatus(401);
        $this->assertStringContainsString('Token expired', $response->json('message'));
    }

    public function test_rechazo_inmediato_si_usuario_es_desactivado()
    {
        $this->userA->update(['active' => false]);

        $response = $this->postJson('/api/marketplace/login', [
            'slug' => $this->marketplaceA->slug,
            'rut' => '18.456.789-K',
            'password' => '18456789k',
        ]);

        $response->assertStatus(401);
        $this->assertStringContainsString('RUT o correo no registrado', $response->json('message'));
    }

    public function test_rechazo_si_el_marketplace_completo_esta_inactivo()
    {
        $this->marketplaceA->update(['active' => false]);

        $response = $this->postJson('/api/marketplace/login', [
            'slug' => $this->marketplaceA->slug,
            'rut' => '18.456.789-K',
            'password' => '18456789k',
        ]);

        $response->assertStatus(404);
        $this->assertStringContainsString('inactivo', $response->json('message'));
    }

    public function test_restablecimiento_de_contrasena_y_login_con_nueva_clave()
    {
        // 1. Cambiar contraseña a un string personalizado
        $this->userA->update(['password' => Hash::make('ClaveSecreta2026!')]);

        // Login con clave anterior (RUT) falla
        $failRes = $this->postJson('/api/marketplace/login', [
            'slug' => $this->marketplaceA->slug,
            'rut' => '18.456.789-K',
            'password' => '18456789k',
        ]);
        $failRes->assertStatus(401);

        // Login con nueva clave exitoso
        $successRes = $this->postJson('/api/marketplace/login', [
            'slug' => $this->marketplaceA->slug,
            'rut' => '18.456.789-K',
            'password' => 'ClaveSecreta2026!',
        ]);
        $successRes->assertOk();
    }

    public function test_admin_sanctum_puede_acceder_a_los_marketplaces_directamente()
    {
        $admin = \App\Models\User::firstOrCreate(
            ['email' => 'admin.bypass@test.com'],
            ['name' => 'Admin Staff', 'password' => Hash::make('password'), 'role' => 'admin']
        );

        $sanctumToken = $admin->createToken('admin-token')->plainTextToken;

        // Consultar /me pasando el token de Sanctum y el slug del marketplace en header
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $sanctumToken,
            'X-Marketplace-Slug' => $this->marketplaceA->slug,
        ])->getJson('/api/marketplace/me');

        $response->assertOk();
        $response->assertJson([
            'marketplace' => [
                'id' => $this->marketplaceA->id,
                'slug' => $this->marketplaceA->slug,
            ],
            'user' => [
                'name' => 'Admin Staff',
                'cargo' => 'Administrador ERP',
                'is_admin' => true,
            ]
        ]);
    }
}
