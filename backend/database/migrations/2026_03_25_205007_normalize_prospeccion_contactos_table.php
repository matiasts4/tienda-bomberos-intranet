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
        Schema::table('prospeccion_contactos', function (Blueprint $table) {
            $table->foreignId('tipo_id')->after('user_id')->nullable()->constrained('prospeccion_tipos')->nullOnDelete();
        });

        // Data migration: map the string type to the new ID
        $types = DB::table('prospeccion_tipos')->pluck('id', 'slug');
        foreach ($types as $slug => $id) {
            DB::table('prospeccion_contactos')
                ->where('tipo', $slug)
                ->update(['tipo_id' => $id]);
        }

        Schema::table('prospeccion_contactos', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('prospeccion_contactos', function (Blueprint $table) {
            $table->enum('tipo', ['no_contesta', 'buzon', 'llamar_luego', 'no_califica', 'calificado'])->after('user_id')->nullable();
        });

        // Reverse data migration
        $types = DB::table('prospeccion_tipos')->pluck('slug', 'id');
        foreach ($types as $id => $slug) {
            DB::table('prospeccion_contactos')
                ->where('tipo_id', $id)
                ->update(['tipo' => $slug]);
        }

        Schema::table('prospeccion_contactos', function (Blueprint $table) {
            $table->dropForeign(['tipo_id']);
            $table->dropColumn('tipo_id');
        });
    }
};
