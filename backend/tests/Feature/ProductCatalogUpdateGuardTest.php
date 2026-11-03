<?php

namespace Tests\Feature;

use App\Models\PteColor;
use App\Models\PteSku;
use App\Models\PteSkuGenerico;
use App\Models\PteTalla;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductCatalogUpdateGuardTest extends TestCase
{
    private function draftGeneric(string $estado = 'borrador'): PteSkuGenerico
    {
        return PteSkuGenerico::query()->create([
            'sku_base' => '111110203020000',
            'nombre' => 'Chaqueta Guardia',
            'precio_venta' => 50000,
            'estado_catalogo' => $estado,
            'activo' => $estado === 'publicado',
        ]);
    }

    private function addActiveVariant(PteSkuGenerico $generic): PteSku
    {
        $color = PteColor::query()->create(['id' => 92, 'nombre' => 'Negro', 'is_activo' => true]);
        $talla = PteTalla::query()->create(['id' => 92, 'nombre' => 'M', 'orden' => 5, 'is_activo' => true]);

        return PteSku::query()->create([
            'sku' => '111110203029292',
            'sku_generico_id' => $generic->id,
            'nombre' => 'Chaqueta Guardia Negro M',
            'color_id' => $color->id,
            'talla_id' => $talla->id,
            'activo' => true,
        ]);
    }

    public function test_update_cannot_publish_via_estado_without_active_variants(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $generic = $this->draftGeneric();

        $this->putJson("/api/sku-genericos/{$generic->id}", ['estado_catalogo' => 'publicado'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('publicar');

        $this->assertDatabaseHas('pte_sku_genericos', [
            'id' => $generic->id,
            'estado_catalogo' => 'borrador',
            'activo' => false,
        ]);
    }

    public function test_update_cannot_publish_via_activo_flag_without_active_variants(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $generic = $this->draftGeneric();

        $this->putJson("/api/sku-genericos/{$generic->id}", ['activo' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors('publicar');

        $this->assertDatabaseHas('pte_sku_genericos', [
            'id' => $generic->id,
            'estado_catalogo' => 'borrador',
            'activo' => false,
        ]);
    }

    public function test_update_publishes_when_generic_has_an_active_variant(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $generic = $this->draftGeneric();
        $this->addActiveVariant($generic);

        $this->putJson("/api/sku-genericos/{$generic->id}", ['estado_catalogo' => 'publicado'])
            ->assertOk()
            ->assertJsonPath('estado_catalogo', 'publicado');

        $this->assertDatabaseHas('pte_sku_genericos', [
            'id' => $generic->id,
            'estado_catalogo' => 'publicado',
            'activo' => true,
        ]);
    }

    public function test_update_cannot_publish_an_archived_generic_directly(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $generic = $this->draftGeneric('archivado');
        $variant = $this->addActiveVariant($generic);
        $variant->update(['activo' => false]);

        $this->putJson("/api/sku-genericos/{$generic->id}", ['estado_catalogo' => 'publicado'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('publicar');

        $this->assertDatabaseHas('pte_sku_genericos', [
            'id' => $generic->id,
            'estado_catalogo' => 'archivado',
            'activo' => false,
        ]);
        $this->assertDatabaseHas('pte_skus', ['sku' => $variant->sku, 'activo' => false]);
    }

    public function test_modal_save_payload_on_published_generic_is_unchanged(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $generic = $this->draftGeneric('publicado');
        $this->addActiveVariant($generic);

        // Mismo payload que envía ManageGenericosModal al guardar.
        $this->putJson("/api/sku-genericos/{$generic->id}", [
            'nombre' => 'Chaqueta Guardia Editada',
            'precio_venta' => 55001,
            'permite_personalizar' => false,
        ])->assertOk();

        $this->assertDatabaseHas('pte_sku_genericos', [
            'id' => $generic->id,
            'nombre' => 'Chaqueta Guardia Editada',
            'precio_venta' => 55000,
            'estado_catalogo' => 'publicado',
            'activo' => true,
        ]);
    }

    public function test_replacing_an_existing_image_succeeds_and_removes_the_old_file(): void
    {
        Storage::fake('public');
        $this->actingAsUser(['role' => 'superadmin']);
        $generic = $this->draftGeneric();

        $this->post("/api/sku-genericos/{$generic->id}/upload", [
            'imagen' => UploadedFile::fake()->image('frente-1.png'),
        ])->assertOk();
        $oldUrl = $generic->refresh()->imagen_url;
        $oldPath = str_replace('/storage/', '', $oldUrl);
        Storage::disk('public')->assertExists($oldPath);

        $this->post("/api/sku-genericos/{$generic->id}/upload", [
            'imagen' => UploadedFile::fake()->image('frente-2.png'),
        ])->assertOk();

        $newUrl = $generic->refresh()->imagen_url;
        $this->assertNotSame($oldUrl, $newUrl);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $newUrl));
    }

    public function test_lifecycle_backfill_archives_previously_inactive_generics(): void
    {
        // Conexión aislada con el esquema previo a la migración: la conexión de pruebas
        // corre dentro de una transacción y ya tiene las columnas de ciclo de vida.
        config(['database.connections.backfill' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        $previous = DB::getDefaultConnection();
        DB::setDefaultConnection('backfill');

        try {
            Schema::create('users', fn ($t) => $t->id());
            Schema::create('pte_sku_genericos', function ($t) {
                $t->id();
                $t->string('sku_base', 15);
                $t->boolean('activo')->default(true);
            });
            Schema::create('pte_skus', function ($t) {
                $t->string('sku')->primary();
                $t->unsignedBigInteger('sku_generico_id');
                $t->boolean('activo')->default(true);
            });

            $publicado = DB::table('pte_sku_genericos')->insertGetId(['sku_base' => '111110203030000', 'activo' => true]);
            $sinVariantes = DB::table('pte_sku_genericos')->insertGetId(['sku_base' => '111110203040000', 'activo' => true]);
            $inactivo = DB::table('pte_sku_genericos')->insertGetId(['sku_base' => '111110203050000', 'activo' => false]);
            DB::table('pte_skus')->insert(['sku' => '111110203039393', 'sku_generico_id' => $publicado, 'activo' => true]);

            $migration = require database_path('migrations/2026_09_03_000001_add_lifecycle_to_pte_sku_genericos_table.php');
            $migration->up();

            $state = DB::table('pte_sku_genericos')->get()->keyBy('id');
            $this->assertSame(['publicado', 1], [$state[$publicado]->estado_catalogo, (int) $state[$publicado]->activo]);
            $this->assertSame(['borrador', 0], [$state[$sinVariantes]->estado_catalogo, (int) $state[$sinVariantes]->activo]);
            $this->assertSame(['archivado', 0], [$state[$inactivo]->estado_catalogo, (int) $state[$inactivo]->activo]);
            $this->assertNotNull($state[$inactivo]->archived_at);
            $this->assertNull($state[$sinVariantes]->archived_at);
        } finally {
            DB::setDefaultConnection($previous);
            DB::purge('backfill');
        }
    }
}
