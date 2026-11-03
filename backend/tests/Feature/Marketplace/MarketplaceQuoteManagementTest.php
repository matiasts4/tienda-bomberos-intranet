<?php

namespace Tests\Feature\Marketplace;

use Tests\TestCase;
use App\Models\User;
use App\Models\Marketplace;
use App\Models\MktProduct;
use App\Models\MktQuote;
use App\Models\MktQuoteItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class MarketplaceQuoteManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Marketplace $marketplace;
    protected MktProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->marketplace = Marketplace::create([
            'name' => 'Bomberos San Clemente',
            'slug' => 'san-clemente',
            'dealer_email' => 'dealer@sanclemente.cl',
            'quote_email' => 'quotes@sanclemente.cl',
            'active' => true,
            'primary_color' => '#E53935',
        ]);

        $this->product = MktProduct::create([
            'sku' => 'CASCO-001',
            'name' => 'Casco F1 Bomberos',
            'price' => 150000,
            'active' => true,
        ]);
        $this->marketplace->products()->attach($this->product->id, ['is_visible' => true]);
    }

    private function createQuote(string $status = 'pending', string $number = 'SCL-2008-001'): MktQuote
    {
        $quote = MktQuote::create([
            'marketplace_id' => $this->marketplace->id,
            'quote_number' => $number,
            'buyer_name' => 'Pedro Bombero',
            'buyer_rut' => '18.456.789-K',
            'buyer_email' => 'pedro@talca.cl',
            'buyer_phone' => '+56911223344',
            'company_unit' => '1ª Cía',
            'status' => $status,
        ]);

        MktQuoteItem::create([
            'quote_id' => $quote->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_sku' => $this->product->sku,
            'quantity' => 2,
            'unit_price' => 150000,
        ]);

        return $quote;
    }

    public function test_admin_can_delete_individual_quote_with_items_cascade()
    {
        Sanctum::actingAs($this->admin);

        $quote = $this->createQuote('pending', 'SCL-2008-DEL');
        $this->assertDatabaseHas('mkt_quotes', ['id' => $quote->id]);
        $this->assertDatabaseHas('mkt_quote_items', ['quote_id' => $quote->id]);

        $res = $this->deleteJson("/api/admin/marketplace/quotes/{$quote->id}");
        $res->assertOk();
        $res->assertJson(['message' => 'Cotización eliminada exitosamente']);

        $this->assertDatabaseMissing('mkt_quotes', ['id' => $quote->id]);
        $this->assertDatabaseMissing('mkt_quote_items', ['quote_id' => $quote->id]);
    }

    public function test_admin_can_bulk_delete_multiple_quotes()
    {
        Sanctum::actingAs($this->admin);

        $q1 = $this->createQuote('pending', 'SCL-2008-001');
        $q2 = $this->createQuote('pending', 'SCL-2008-002');
        $q3 = $this->createQuote('pending', 'SCL-2008-003');

        $res = $this->postJson('/api/admin/marketplace/quotes/bulk-delete', [
            'ids' => [$q1->id, $q2->id],
        ]);

        $res->assertOk();
        $res->assertJson(['message' => '2 cotizaciones eliminadas exitosamente']);

        $this->assertDatabaseMissing('mkt_quotes', ['id' => $q1->id]);
        $this->assertDatabaseMissing('mkt_quotes', ['id' => $q2->id]);
        $this->assertDatabaseHas('mkt_quotes', ['id' => $q3->id]);
    }

    public function test_admin_can_bulk_update_status()
    {
        Sanctum::actingAs($this->admin);

        $q1 = $this->createQuote('pending', 'SCL-2008-001');
        $q2 = $this->createQuote('pending', 'SCL-2008-002');

        $res = $this->postJson('/api/admin/marketplace/quotes/bulk-status', [
            'ids' => [$q1->id, $q2->id],
            'status' => 'reviewed',
        ]);

        $res->assertOk();

        $this->assertDatabaseHas('mkt_quotes', ['id' => $q1->id, 'status' => 'reviewed']);
        $this->assertDatabaseHas('mkt_quotes', ['id' => $q2->id, 'status' => 'reviewed']);
    }

    public function test_quotes_can_be_filtered_by_unread_and_read()
    {
        Sanctum::actingAs($this->admin);

        $this->createQuote('pending', 'SCL-2008-001');
        $this->createQuote('unread', 'SCL-2008-002');
        $this->createQuote('reviewed', 'SCL-2008-003');
        $this->createQuote('closed', 'SCL-2008-004');

        // Filtrar por unread (debe retornar 2)
        $resUnread = $this->getJson('/api/admin/marketplace/quotes?status=unread');
        $resUnread->assertOk();
        $this->assertEquals(2, count($resUnread->json('data')));

        // Filtrar por read (debe retornar 2)
        $resRead = $this->getJson('/api/admin/marketplace/quotes?status=read');
        $resRead->assertOk();
        $this->assertEquals(2, count($resRead->json('data')));
    }
}
