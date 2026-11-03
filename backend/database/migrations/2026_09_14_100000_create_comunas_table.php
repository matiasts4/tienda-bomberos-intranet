<?php

use App\Support\ComunasChile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('comunas')) {
            Schema::create('comunas', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 120);
                $table->foreignId('region_id')->constrained('regiones')->cascadeOnDelete();
                $table->foreignId('provincia_id')->nullable()->constrained('provincias')->nullOnDelete();
                $table->timestamps();

                $table->unique(['region_id', 'nombre']);
            });
        }

        $this->poblar();
    }

    /**
     * Carga el catálogo de las 346 comunas. Es idempotente (updateOrInsert), así
     * que se puede volver a correr sin duplicar ni pisar datos ya enlazados.
     */
    private function poblar(): void
    {
        // Solo poblamos las regiones que realmente existen en esta BD: en
        // instalaciones nuevas la tabla `regiones` puede venir vacía.
        $regionesExistentes = DB::table('regiones')->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (empty($regionesExistentes)) {
            return;
        }

        // provincia_id es un "nice to have": lo enlazamos cuando la provincia ya
        // está cargada, comparando por nombre dentro de la misma región.
        $provincias = DB::table('provincias')
            ->get(['id', 'nombre', 'region_id'])
            ->keyBy(fn ($p) => ((int) $p->region_id) . '|' . $this->normalizar($p->nombre));

        $now = now();

        foreach (ComunasChile::planas() as $comuna) {
            if (!in_array($comuna['region_id'], $regionesExistentes, true)) {
                continue;
            }

            $claveProvincia = $comuna['region_id'] . '|' . $this->normalizar($comuna['provincia']);
            $provinciaId = $provincias->get($claveProvincia)->id ?? null;

            $existente = DB::table('comunas')
                ->where('region_id', $comuna['region_id'])
                ->where('nombre', $comuna['nombre'])
                ->first(['id', 'provincia_id']);

            if (!$existente) {
                DB::table('comunas')->insert([
                    'region_id' => $comuna['region_id'],
                    'nombre' => $comuna['nombre'],
                    'provincia_id' => $provinciaId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                continue;
            }

            // Solo completamos el enlace a provincia si faltaba; nunca lo pisamos.
            if ($provinciaId && !$existente->provincia_id) {
                DB::table('comunas')
                    ->where('id', $existente->id)
                    ->update(['provincia_id' => $provinciaId, 'updated_at' => $now]);
            }
        }
    }

    private function normalizar(?string $valor): string
    {
        $valor = mb_strtolower(trim((string) $valor));

        return strtr($valor, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('comunas');
    }
};
