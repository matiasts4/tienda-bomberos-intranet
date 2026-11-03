<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('despachos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones');
            $table->string('cliente_id', 15);
            $table->foreignId('estado_id')->nullable()->constrained('estados');
            $table->string('calle', 150)->nullable();
            $table->string('numero_calle', 20)->nullable();
            $table->string('comuna', 100)->nullable();
            $table->string('ciudad', 100)->nullable();
            $table->string('agencia', 100)->nullable();
            $table->string('voucher_url')->nullable();
            $table->date('fecha_despacho')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
        (require database_path('migrations/2026_08_31_041336_make_despacho_cliente_nullable_for_direct_quotes.php'))->up();
    }

    public function down(): void
    {
        Schema::dropIfExists('despachos');
    }
};
