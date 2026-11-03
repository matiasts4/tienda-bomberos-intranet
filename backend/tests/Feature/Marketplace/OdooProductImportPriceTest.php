<?php

namespace Tests\Feature\Marketplace;

use Tests\TestCase;
use App\Models\MktProduct;
use App\Models\MktCategory;
use Illuminate\Http\UploadedFile;

class OdooProductImportPriceTest extends TestCase
{
    public function test_admin_can_create_product_with_price(): void
    {
        $this->actingAsAdmin();
        $category = MktCategory::create([
            'name' => 'Vestuario Bomberos',
            'slug' => 'vestuario-bomberos'
        ]);

        $response = $this->postJson('/api/admin/marketplace/products', [
            'sku' => 'TEST-PRICE-001',
            'name' => 'Casco F1 XF',
            'category_id' => $category->id,
            'price' => 45990.50,
            'active' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('mkt_products', [
            'sku' => 'TEST-PRICE-001',
            'name' => 'Casco F1 XF',
            'price' => 45990.50,
        ]);
    }

    public function test_admin_can_update_product_price(): void
    {
        $this->actingAsAdmin();
        $product = MktProduct::create([
            'sku' => 'TEST-PRICE-002',
            'name' => 'Botas Haix',
            'price' => 120000,
            'active' => true,
        ]);

        $response = $this->putJson("/api/admin/marketplace/products/{$product->id}", [
            'price' => 135990,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('mkt_products', [
            'id' => $product->id,
            'price' => 135990.00,
        ]);
    }

    public function test_odoo_csv_import_reads_list_price_and_categories(): void
    {
        $this->actingAsAdmin();
        MktCategory::firstOrCreate([
            'slug' => 'souvenir-bomberos'
        ], [
            'name' => 'Souvenir Bomberos'
        ]);

        $csvContent = implode("\n", [
            'id,default_code,name,type,sale_ok,purchase_ok,list_price,Precio de venta,Categoría del producto,website_published,description_sale,website_description,description,weight,image_1920,website_slug,public_categ_ids,meta_title,meta_description_attribute',
            'TB_MOCH1000000NGRCOLOR,MOCH1000000NGRCOLOR,Mochila Bomberos Notebook,product,1,1,22990,,Magento / Souvenir Bomberos,1,Mochila Bomberos,Mochila Porta Notebook,,,,/mochila-bomberos-notebook,"Souvenir Bomberos",,'
        ]);

        $file = UploadedFile::fake()->createWithContent('odoo_test.csv', $csvContent);

        $response = $this->postJson('/api/admin/marketplace/products/import-odoo-excel', [
            'file' => $file,
        ]);

        $response->assertOk();
        
        $this->assertDatabaseHas('mkt_products', [
            'sku' => 'TB_MOCH1000000NGRCOLOR',
            'name' => 'Mochila Bomberos Notebook',
            'price' => 22990.00,
        ]);
    }

    public function test_odoo_csv_import_reads_precio_de_venta_and_dot_decimal(): void
    {
        $this->actingAsAdmin();
        MktCategory::create([
            'name' => 'Accesorios',
            'slug' => 'accesorios'
        ]);

        $csvContent = implode("\n", [
            'id,default_code,name,type,sale_ok,purchase_ok,list_price,Precio de venta,Categoría del producto,website_published,description_sale,website_description,description,weight,image_1920,website_slug,public_categ_ids',
            'TB_ACC001,ACC001,Linterna Stealth,product,1,1,,34990.00,Accesorios,1,Linterna LED,,,,,,Accesorios'
        ]);

        $file = UploadedFile::fake()->createWithContent('odoo_test_2.csv', $csvContent);

        $response = $this->postJson('/api/admin/marketplace/products/import-odoo-excel', [
            'file' => $file,
        ]);

        $response->assertOk();
        
        $this->assertDatabaseHas('mkt_products', [
            'sku' => 'TB_ACC001',
            'name' => 'Linterna Stealth',
            'price' => 34990.00,
        ]);
    }
}
