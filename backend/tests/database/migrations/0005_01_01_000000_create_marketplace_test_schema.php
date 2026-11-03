<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mkt_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('image_url')->nullable();
            $table->timestamps();
        });

        Schema::create('marketplaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('dealer_email');
            $table->string('quote_email')->nullable();
            $table->string('internal_email')->nullable();
            $table->string('password')->nullable();
            $table->string('logo_url')->nullable();
            $table->boolean('active')->default(true);
            $table->string('primary_color')->nullable();
            $table->timestamps();
        });

        Schema::create('mkt_products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category_id')->nullable();
            $table->json('images_json')->nullable();
            $table->json('variants_json')->nullable();
            $table->decimal('price', 12, 2)->nullable()->default(0);
            $table->boolean('active')->default(true);
            $table->json('tech_specs')->nullable();
            $table->timestamps();
        });

        Schema::create('marketplace_product', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_id');
            $table->unsignedBigInteger('product_id');
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('mkt_quotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_id');
            $table->string('quote_number')->unique();
            $table->string('buyer_name');
            $table->string('buyer_rut');
            $table->string('buyer_email');
            $table->string('buyer_phone');
            $table->string('company_unit');
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('mkt_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_id');
            $table->string('name');
            $table->string('rut');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company_unit')->nullable();
            $table->string('cargo')->nullable();
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('mkt_quote_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quote_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name');
            $table->string('product_sku');
            $table->decimal('unit_price', 12, 2)->nullable()->default(0);
            $table->integer('quantity');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('mkt_category_product', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('product_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_category_product');
        Schema::dropIfExists('mkt_quote_items');
        Schema::dropIfExists('mkt_users');
        Schema::dropIfExists('mkt_quotes');
        Schema::dropIfExists('marketplace_product');
        Schema::dropIfExists('mkt_products');
        Schema::dropIfExists('marketplaces');
        Schema::dropIfExists('mkt_categories');
    }
};
