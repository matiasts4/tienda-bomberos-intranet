<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Modificar la columna para permitir NULL (Precio Pendiente)
        Schema::table('mp_proveedor_productos', function (Blueprint $table) {
            $table->decimal('precio_referencia', 10, 2)->nullable()->change();
        });

        // 2. Obtener pares únicos de (proveedor_id, material_id) desde el historial de lotes
        // que NO existan ya en la tabla pivote mp_proveedor_productos.

        $results = DB::select("
            SELECT DISTINCT lote.proveedor_id, lote.material_id
            FROM mp_lotes as lote
            LEFT JOIN mp_proveedor_productos as pivot 
                ON pivot.proveedor_id = lote.proveedor_id 
                AND pivot.material_id = lote.material_id
            WHERE lote.proveedor_id IS NOT NULL 
              AND pivot.id IS NULL
        ");

        $now = now();
        $records = [];

        foreach ($results as $row) {
            $records[] = [
                'proveedor_id' => $row->proveedor_id,
                'material_id' => $row->material_id,
                'sku_proveedor' => null, // Se deja nulo para llenado manual
                'precio_referencia' => null, // Se deja nulo para llenado manual
                'moneda' => 'CLP',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($records)) {
            DB::table('mp_proveedor_productos')->insert($records);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir el cambio de nulabilidad
        Schema::table('mp_proveedor_productos', function (Blueprint $table) {
            $table->decimal('precio_referencia', 10, 2)->default(0)->change();
        });
    }
};
