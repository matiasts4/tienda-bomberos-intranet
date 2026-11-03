<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cuerpos', function (Blueprint $table) {
            $table->string('id', 15)->primary(); // e.g. 0515700
            $table->foreignId('region_id')->nullable()->constrained('regiones')->nullOnDelete();
            $table->string('nombre');
            $table->integer('numero_socio')->nullable();

            $table->string('rut')->nullable();
            $table->date('fecha_fundacion')->nullable();
            $table->string('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->string('superintendente')->nullable();
            $table->string('comandante')->nullable();
            $table->integer('numero_companias')->default(0);
            $table->string('logo_url')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuerpos');
    }
};
