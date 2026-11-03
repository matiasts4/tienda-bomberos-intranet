<?php

namespace Tests\Feature\Marketplace;

use Tests\TestCase;
use App\Models\Marketplace;
use App\Models\MktProduct;
use App\Models\MktCategory;
use App\Models\MktQuote;
use App\Mail\MktQuoteCreatedMail;
use App\Mail\MktQuoteCustomerConfirmationMail;
use Illuminate\Support\Facades\Crypt;

class MarketplaceQuoteEnhancementsTest extends TestCase
{
    private function createMarketplaceWithProduct($price = 25000)
    {
        $marketplace = Marketplace::create([
            'name' => 'Marketplace Test',
            'slug' => 'mkt-test-' . uniqid(),
            'dealer_email' => 'dealer@test.com',
            'quote_email' => 'quotes@test.com',
            'internal_email' => 'internal@test.com',
        ]);

        $cat = MktCategory::create([
            'name' => 'Cat ' . uniqid(),
            'slug' => 'cat-' . uniqid()
        ]);

        $product = MktProduct::create([
            'name' => 'Chaqueta Bomber Test',
            'sku' => 'BOMBER-' . uniqid(),
            'price' => $price,
            'category_id' => $cat->id,
            'active' => true,
        ]);

        $marketplace->products()->attach($product->id, ['is_visible' => true]);

        $tokenPayload = json_encode([
            'marketplace_id' => $marketplace->id,
            'slug' => $marketplace->slug,
            'expires_at' => now()->addHours(24)->timestamp
        ]);
        $token = Crypt::encryptString($tokenPayload);

        return [$marketplace, $product, $token];
    }

    public function test_rechaza_rut_invalido()
    {
        [$marketplace, $product, $token] = $this->createMarketplaceWithProduct();

        // 11.111.111-2 tiene dígito verificador incorrecto (el correcto es 1)
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/marketplace/quotes', [
                'buyer_name' => 'Juan Perez',
                'buyer_rut' => '11.111.111-2',
                'buyer_email' => 'juan@test.com',
                'buyer_phone' => '+56912345678',
                'company_unit' => '1ra Compañía',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2]
                ]
            ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['buyer_rut']);
    }

    public function test_acepta_rut_valido_y_guarda_precio_unitario()
    {
        [$marketplace, $product, $token] = $this->createMarketplaceWithProduct(35000);

        // 11.111.111-1 es un RUT chileno válido
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/marketplace/quotes', [
                'buyer_name' => 'Juan Perez',
                'buyer_rut' => '11.111.111-1',
                'buyer_email' => 'juan@test.com',
                'buyer_phone' => '+56912345678',
                'company_unit' => '1ra Compañía',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 3, 'notes' => 'Talla: L']
                ]
            ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['message', 'quote_number']);

        $quote = MktQuote::where('quote_number', $response->json('quote_number'))->first();
        $this->assertNotNull($quote);
        $this->assertEquals('11.111.111-1', $quote->buyer_rut);

        // Validar formato: ABREV-DDMM-001
        $expectedPrefix = 'DEM'; // De "Dealer Demo"
        $expectedDate = date('dm');
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{3}-\d{4}-\d{3}$/', $quote->quote_number);
        $this->assertStringContainsString($expectedDate, $quote->quote_number);

        $item = $quote->items()->first();
        $this->assertNotNull($item);
        $this->assertEquals(35000, floatval($item->unit_price));
        $this->assertEquals(3, $item->quantity);
        $this->assertEquals('Talla: L', $item->notes);
    }

    public function test_formato_quote_number_cuerpo_san_clemente()
    {
        // Crear marketplace con nombre "San Clemente"
        $slug = 'san-clemente-' . uniqid();
        $marketplace = Marketplace::create([
            'name' => 'Bomberos San Clemente',
            'slug' => $slug,
            'dealer_email' => 'dealer.sc@test.com',
            'quote_email' => 'ventas.sc@test.com',
            'password' => bcrypt('password'),
            'active' => true,
        ]);

        $product = MktProduct::create([
            'sku' => 'TEST-SC-01',
            'name' => 'Botas Fire',
            'price' => 50000,
            'active' => true,
        ]);

        $marketplace->products()->attach($product->id, ['is_visible' => true]);
        
        $tokenPayload = json_encode([
            'marketplace_id' => $marketplace->id,
            'slug' => $marketplace->slug,
            'expires_at' => now()->addHours(24)->timestamp
        ]);
        $token = Crypt::encryptString($tokenPayload);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/marketplace/quotes', [
                'buyer_name' => 'Pedro Aguirre',
                'buyer_rut' => '11.111.111-1',
                'buyer_email' => 'pedro@test.com',
                'buyer_phone' => '+56912345678',
                'company_unit' => 'Primera Compañía',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1]
                ]
            ]);

        $response->assertStatus(201);
        $quoteNum = $response->json('quote_number');
        $dayMonth = date('dm');

        // Formato esperado para San Clemente: SCL-DDMM-001
        $this->assertEquals("SCL-{$dayMonth}-001", $quoteNum);
    }

    public function test_render_correos_sin_excepciones_con_agrupacion_y_precios()
    {
        [$marketplace, $product, $token] = $this->createMarketplaceWithProduct(45000);

        $quote = MktQuote::create([
            'marketplace_id' => $marketplace->id,
            'quote_number' => 'MP-TEST-2608',
            'buyer_name' => 'Carlos Valdivia',
            'buyer_rut' => '11.111.111-1',
            'buyer_email' => 'carlos@test.com',
            'buyer_phone' => '+56987654321',
            'company_unit' => 'Segunda Compañía',
            'address' => 'Av. Libertad 456',
            'notes' => 'Despachar urgente por favor',
            'status' => 'pending',
        ]);

        // Simular múltiples variantes (tallas) del mismo producto
        $quote->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => 45000,
            'quantity' => 2,
            'notes' => 'Talla: M',
        ]);

        $quote->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => 45000,
            'quantity' => 3,
            'notes' => 'Talla: L | Bordado logo espalda',
        ]);

        // 1. Renderizar correo para el admin/distribuidor
        $adminMail = new MktQuoteCreatedMail($quote);
        $adminHtml = $adminMail->render();
        
        $this->assertNotEmpty($adminHtml);
        $this->assertStringContainsString('NUEVA COTIZACIÓN RECIBIDA', $adminHtml);
        $this->assertStringContainsString('Chaqueta Bomber Test', $adminHtml);
        $this->assertStringContainsString('Talla: M', $adminHtml);
        $this->assertStringContainsString('Talla: L | Bordado logo espalda', $adminHtml);
        $this->assertStringContainsString('TOTAL ESTIMADO', $adminHtml);
        $this->assertStringContainsString('225.000', $adminHtml); // 5 * 45000 = 225000
        // Asegurarse de que no esté el encabezado SKU en la tabla
        $this->assertStringNotContainsString('<th style=\'padding: 12px 10px; border-bottom: 2px solid #cbd5e1; font-size: 12px; color: #475569; font-weight: 700; text-transform: uppercase;\'>SKU</th>', $adminHtml);

        // 2. Renderizar correo para el cliente final
        $customerMail = new MktQuoteCustomerConfirmationMail($quote);
        $customerHtml = $customerMail->render();

        $this->assertNotEmpty($customerHtml);
        $this->assertStringContainsString('¡Solicitud Recibida!', $customerHtml);
        $this->assertStringContainsString('Carlos', $customerHtml);
        $this->assertStringContainsString('Chaqueta Bomber Test', $customerHtml);
        $this->assertStringContainsString('225.000', $customerHtml);
    }

    public function test_soporte_multicorreo_en_cotizaciones_y_validacion()
    {
        $marketplace = Marketplace::create([
            'name' => 'Marketplace MultiEmail',
            'slug' => 'mkt-multi-' . uniqid(),
            'dealer_email' => 'ventas@cuerpo.cl',
            'quote_email' => 'ventas@cuerpo.cl, comandante@cuerpo.cl; tesoreria@cuerpo.cl',
            'internal_email' => 'admin1@tb.cl, admin2@tb.cl',
            'active' => true,
        ]);

        $cat = MktCategory::create(['name' => 'Cat ' . uniqid(), 'slug' => 'cat-' . uniqid()]);
        $product = MktProduct::create([
            'name' => 'Casco F1',
            'sku' => 'CASCO-' . uniqid(),
            'price' => 80000,
            'category_id' => $cat->id,
            'active' => true,
        ]);
        $marketplace->products()->attach($product->id, ['is_visible' => true]);

        $tokenPayload = json_encode([
            'marketplace_id' => $marketplace->id,
            'slug' => $marketplace->slug,
            'expires_at' => now()->addHours(24)->timestamp
        ]);
        $token = \Illuminate\Support\Facades\Crypt::encryptString($tokenPayload);

        \Illuminate\Support\Facades\Mail::fake();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/marketplace/quotes', [
                'buyer_name' => 'Pedro Bombero',
                'buyer_rut' => '11.111.111-1',
                'buyer_email' => 'pedro@bomberos.cl',
                'buyer_phone' => '+56911223344',
                'company_unit' => 'Tercera Compañía',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2]
                ]
            ]);

        $response->assertStatus(201);

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\MktQuoteCreatedMail::class, function ($mail) {
            return $mail->hasTo('ventas@cuerpo.cl') &&
                   $mail->hasTo('comandante@cuerpo.cl') &&
                   $mail->hasTo('tesoreria@cuerpo.cl') &&
                   $mail->hasBcc('admin1@tb.cl') &&
                   $mail->hasBcc('admin2@tb.cl');
        });

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\MktQuoteCustomerConfirmationMail::class, function ($mail) {
            return $mail->hasTo('pedro@bomberos.cl');
        });
    }
}
