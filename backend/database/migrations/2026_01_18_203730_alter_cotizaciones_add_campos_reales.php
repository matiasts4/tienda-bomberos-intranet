<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            // 1) Permitir cotización sin oportunidad (cotización particular)
            $table->foreignId('oportunidad_id')->nullable()->change();

            // 2) Número/código visible tipo "11717" o "CT-2026-0001"
            $table->string('numero', 32)->nullable()->unique()->after('id');

            // 3) Totales de cabecera (en CLP enteros)
            $table->unsignedBigInteger('total_neto')->default(0)->after('observaciones');
            $table->unsignedBigInteger('iva')->default(0)->after('total_neto');
            $table->unsignedBigInteger('total')->default(0)->after('iva');

            // 4) Condiciones del Excel
            $table->string('plazo_produccion', 120)->nullable()->after('total');
            $table->string('condiciones_pago', 120)->nullable()->after('plazo_produccion');
            $table->string('despacho', 120)->nullable()->after('condiciones_pago');

            // 5) Origen (manual / oportunidad / excel)
            $table->string('origen', 20)->default('manual')->after('despacho');
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn([
                'numero',
                'total_neto',
                'iva',
                'total',
                'plazo_produccion',
                'condiciones_pago',
                'despacho',
                'origen',
            ]);

            // Volver a obligatorio (solo si te interesa revertir; puede fallar si ya hay nulls)
            $table->foreignId('oportunidad_id')->nullable(false)->change();
        });
    }
};
