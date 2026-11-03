<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encuesta_qrs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('referencia_clave', 100)->index();
            $t->string('origen', 20);
            $t->foreignId('cotizacion_id')->nullable()->constrained('cotizaciones')->nullOnDelete();
            $t->foreignId('peticion_id')->nullable()->constrained('peticiones')->nullOnDelete();
            $t->foreignId('contacto_id')->nullable()->constrained('contactos')->nullOnDelete();
            $t->string('cliente_id', 15)->nullable();
            $t->foreign('cliente_id')->references('id')->on('clientes')->nullOnDelete();
            $t->string('referencia', 100);
            $t->string('nombre_venta')->nullable();
            $t->string('cliente_nombre')->nullable();
            $t->string('empresa_cliente')->nullable();
            $t->foreignId('vendedor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('vendedor_nombre')->nullable();
            $t->foreignId('generado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('token');
            $t->foreignId('encuesta_id')->nullable()->constrained('encuesta_satisfaccions')->nullOnDelete();
            $t->timestamp('respondida_at')->nullable()->index();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('No se elimina el historial de QR. Revertir código conservando esta tabla.');
    }
};
