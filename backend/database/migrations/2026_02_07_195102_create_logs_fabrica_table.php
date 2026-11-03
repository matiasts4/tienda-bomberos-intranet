<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('logs_fabrica', function (Blueprint $table) {
            $table->id();
            $table->string('action'); // e.g. "package_created", "status_updated"
            $table->text('description'); // Human readable log
            $table->json('details')->nullable(); // JSON data
            $table->string('entity_type')->nullable(); // "Package", "CuttingOrder"
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // Optional user tracking
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs_fabrica');
    }
};
