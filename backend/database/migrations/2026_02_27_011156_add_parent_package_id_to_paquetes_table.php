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
        Schema::table('paquetes', function (Blueprint $table) {
            // Referencia al paquete padre (para paquetes de reparación)
            $table->unsignedBigInteger('parent_package_id')->nullable()->after('tracking_number');
            $table->foreign('parent_package_id')->references('id')->on('paquetes')->onDelete('set null');
            
            // Indicador de si es un paquete de reparación
            $table->boolean('is_repair_package')->default(false)->after('parent_package_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paquetes', function (Blueprint $table) {
            $table->dropForeign(['parent_package_id']);
            $table->dropColumn(['parent_package_id', 'is_repair_package']);
        });
    }
};
