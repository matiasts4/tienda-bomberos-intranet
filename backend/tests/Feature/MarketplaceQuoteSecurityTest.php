<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Marketplace;
use App\Models\MktProduct;
use App\Models\MktCategory;

class MarketplaceQuoteSecurityTest extends TestCase
{
    public function test_no_puede_cotizar_producto_ajeno()
    {
        $u = uniqid();
        $marketplace1 = Marketplace::create([
            'name' => 'MK1',
            'slug' => 'mk1-' . $u,
            'dealer_email' => 'dealer1@test.com'
        ]);

        $marketplace2 = Marketplace::create([
            'name' => 'MK2',
            'slug' => 'mk2-' . $u,
            'dealer_email' => 'dealer2@test.com'
        ]);
        
        $cat = MktCategory::create(['name' => 'Cat ' . $u, 'slug' => 'cat-' . $u]);

        $product = MktProduct::create([
            'name' => 'Prod',
            'sku' => 'PROD1',
            'category_id' => $cat->id
        ]);
        
        // Asignado al 1, pero no al 2
        $marketplace1->products()->attach($product->id, ['is_visible' => true]);

        $tokenPayload = json_encode([
            'marketplace_id' => $marketplace2->id,
            'slug' => $marketplace2->slug,
            'expires_at' => now()->addHours(24)->timestamp
        ]);
        $token = \Illuminate\Support\Facades\Crypt::encryptString($tokenPayload);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/marketplace/quotes', [
                'buyer_name' => 'John',
                'buyer_rut' => '11.111.111-1',
                'buyer_email' => 'test@test.com',
                'buyer_phone' => '1234',
                'company_unit' => 'Unit',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1]
                ]
            ]);

        $response->assertStatus(422);
    }
}
