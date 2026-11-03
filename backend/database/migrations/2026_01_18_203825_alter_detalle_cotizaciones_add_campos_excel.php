<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('detalle_cotizaciones', function (Blueprint $table) {
            // En el Excel existen estas columnas
            $table->string('producto', 120)->nullable()->after('sku');
            $table->string('talla', 30)->nullable()->after('producto');
            $table->string('color', 60)->nullable()->after('talla');
            $table->string('genero', 30)->nullable()->after('color');

            // Ej: Bordado / Estampado / Sublimación
            $table->string('tipo_personalizacion', 60)->nullable()->after('genero');

            // Precio unitario y total neto por línea (en CLP enteros)
            $table->unsignedBigInteger('precio_unitario')->nullable()->after('cantidad');
            $table->unsignedBigInteger('total_neto')->nullable()->after('precio_unitario');

            // Tu columna "subtotal" ya existe, pero en tu esquema es ambiguo.
            // Si hoy la usas, déjala; si no, en el futuro la puedes deprecar.
        });
    }

    public function down(): void
    {
        Schema::table('detalle_cotizaciones', function (Blueprint $table) {
            $table->dropColumn([
                'producto',
                'talla',
                'color',
                'genero',
                'tipo_personalizacion',
                'precio_unitario',
                'total_neto',
            ]);
        });
    }
};
