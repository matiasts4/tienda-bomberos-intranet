<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Réplica de 2026_07_03_000001: ClienteController::store guarda `tipo`.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('clientes', 'tipo')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->string('tipo', 20)->default('bomberos');
            });
        }
    }
};
