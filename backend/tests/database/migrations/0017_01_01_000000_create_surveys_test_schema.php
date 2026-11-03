<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Esquema final histórico: evita los alters MySQL de mayo al probar con SQLite.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encuesta_satisfaccions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cotizacion_id')->nullable()->constrained('cotizaciones');
            $t->string('cliente_id', 15)->nullable();
            $t->string('identificador_venta', 100)->nullable();
            $t->foreignId('vendedor_id')->nullable()->constrained('users');
            $t->string('vendedor_manual', 100)->nullable();
            $t->string('nombre_cliente_manual', 100)->nullable();
            $t->string('nombre_venta')->nullable();
            foreach (['calidad_producto', 'calidad_personalizacion', 'tiempo_entrega', 'atencion_comercial'] as $field) {
                $t->tinyInteger($field);
            }
            $t->text('comentarios')->nullable();
            $t->timestamps();
        });
    }
};
