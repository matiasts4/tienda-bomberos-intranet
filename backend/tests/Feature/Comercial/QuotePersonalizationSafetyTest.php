<?php

namespace Tests\Feature\Comercial;

use App\Models\{Cotizacion, DetalleCotizacion, Personalizacion, PteSkuGenerico, PteSku};
use App\Services\QuotePersonalization;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuotePersonalizationSafetyTest extends TestCase
{
    private function quote(): Cotizacion
    {
        $states = $this->seedEstadosCotizacion();
        $user = $this->actingAsUser(['role' => 'superadmin']);
        return Cotizacion::factory()->forUser($user)->withEstado($states['Aprobada por Cliente'])->create();
    }

    private function config(int $quantity = 2): array
    {
        return [[
            'nombre_grupo' => 'Grupo A',
            'tallas' => [['genero' => 'Unisex', 'color' => 'Azul', 'talla' => 'M', 'cantidad' => $quantity]],
            'matrices' => [['posicion' => 'Frente', 'tecnica' => 'Bordado', 'tipo_contenido' => 'texto_fijo', 'valor_fijo' => 'BOMBEROS']],
            'variables' => [],
        ]];
    }

    private function item(Cotizacion $quote, array $attributes = []): DetalleCotizacion
    {
        return DetalleCotizacion::factory()->forCotizacion($quote)->create(array_merge([
            'producto' => 'Prenda gratuita', 'cantidad' => 2, 'precio_unitario' => 0,
            'configuracion' => $this->config(), 'tipo_personalizacion' => 'bordado',
        ], $attributes));
    }

    private function save(Cotizacion $quote, array $items, bool $send = true)
    {
        return $this->putJson("/api/cotizaciones/{$quote->id}/personalizacion-configuracion", ['items' => $items, 'enviar' => $send]);
    }

    public function test_informational_lines_remain_in_quote_but_are_excluded_even_with_legacy_configuration(): void
    {
        $quote = $this->quote();
        PteSkuGenerico::create(['sku_base' => '117700000400000', 'nombre' => 'BANDERA', 'es_informativo_cotizacion' => true]);
        $flag = $this->item($quote, ['sku' => '117700000400000', 'producto' => 'BANDERA']);
        $real = $this->item($quote);
        $this->getJson("/api/cotizacion-items/{$flag->id}/personalizacion")->assertOk()->assertJsonPath('es_informativo_cotizacion', true);
        $this->save($quote, [['id' => $real->id, 'grupos' => $this->config()]])->assertOk();
        $this->assertSame(1, Personalizacion::count());
        $this->assertSame('Prenda gratuita', Personalizacion::first()->nombre_producto);
        $this->assertSame(2, $quote->detalles()->count());
        $this->assertSame('BANDERA', $flag->fresh()->producto);
        $this->assertEquals(0, $flag->fresh()->precio_unitario);
        // El mismo conjunto usado por el PDF sigue conteniendo ambos ítems.
        $this->getJson("/api/cotizaciones/{$quote->id}")->assertOk()->assertJsonCount(2, 'detalles');
    }

    public function test_migration_marks_only_the_nine_exact_base_skus_not_names_prices_or_kits(): void
    {
        $skus = ['117700000100000','117700000200000','117700000300000','117700000400000','117700000500000','117700000600000','117700000700000','117700000800000','117700000900000'];
        foreach ($skus as $sku) PteSkuGenerico::create(['sku_base' => $sku, 'nombre' => 'Nombre cambiado']);
        $other = PteSkuGenerico::create(['sku_base' => '999999999900000', 'nombre' => 'BANDERA', 'precio_venta' => 0]);
        $migration = require database_path('migrations/2026_08_31_034903_add_es_informativo_cotizacion_to_pte_sku_genericos_table.php');
        $migration->down();
        $migration->up();
        $this->assertSame(9, PteSkuGenerico::where('es_informativo_cotizacion', true)->count());
        $this->assertFalse($other->fresh()->es_informativo_cotizacion);
    }

    public function test_exclusion_also_follows_the_formal_variant_relationship(): void
    {
        $quote = $this->quote();
        $generic = PteSkuGenerico::create(['sku_base' => '117700000400000', 'nombre' => 'BANDERA', 'es_informativo_cotizacion' => true]);
        $color = DB::table('pte_colores')->insertGetId(['nombre' => 'Azul']);
        $size = DB::table('pte_tallas')->insertGetId(['nombre' => 'M']);
        PteSku::create(['sku' => '117700000400101', 'nombre' => 'Bandera variante', 'sku_generico_id' => $generic->id, 'color_id' => $color, 'talla_id' => $size]);
        $item = $this->item($quote, ['sku' => '117700000400101']);
        $this->assertTrue(QuotePersonalization::informational($item));
        $this->putJson("/api/cotizacion-items/{$item->id}/personalizacion", ['grupos' => $this->config()])->assertStatus(422);
    }

    public function test_invalid_last_item_rolls_back_the_whole_save(): void
    {
        $quote = $this->quote();
        $first = $this->item($quote);
        $second = $this->item($quote);
        $changed = $this->config(); $changed[0]['nombre_grupo'] = 'No debe guardarse';
        $this->save($quote, [['id' => $first->id, 'grupos' => $changed], ['id' => $second->id, 'grupos' => $this->config(1)]])->assertStatus(422);
        $this->assertSame('Grupo A', $first->fresh()->configuracion[0]['nombre_grupo']);
        $this->assertSame(0, Personalizacion::count());
        $this->assertNull($quote->fresh()->estado_personalizacion);
    }

    public function test_missing_or_foreign_item_cannot_be_silently_saved(): void
    {
        $quote = $this->quote(); $first = $this->item($quote); $this->item($quote);
        $this->save($quote, [['id' => $first->id, 'grupos' => $this->config()]], false)->assertStatus(422);
        $this->save($quote, [['id' => 99999, 'grupos' => $this->config()]], false)->assertStatus(422);
    }

    public function test_repeat_send_reconfiguration_and_item_edits_are_blocked_after_dispatch(): void
    {
        $quote = $this->quote(); $item = $this->item($quote);
        $payload = [['id' => $item->id, 'grupos' => $this->config()]];
        $this->save($quote, $payload)->assertOk();
        $this->save($quote, $payload)->assertStatus(409);
        $this->postJson("/api/cotizaciones/{$quote->id}/send-personalizacion")->assertStatus(409);
        $this->patchJson("/api/cotizaciones/{$quote->id}/personalizacion-completada")->assertStatus(409);
        $this->putJson("/api/cotizacion-items/{$item->id}/personalizacion", ['grupos' => []])->assertStatus(409);
        $this->assertSame(1, Personalizacion::count());
    }

    public function test_draft_can_be_incomplete_but_send_requires_matrix_content_and_positive_quantities(): void
    {
        $quote = $this->quote(); $item = $this->item($quote);
        $groups = $this->config(1); $groups[0]['matrices'][0]['valor_fijo'] = '';
        $this->save($quote, [['id' => $item->id, 'grupos' => $groups]], false)->assertOk();
        $this->assertNull($quote->fresh()->estado_personalizacion);
        $this->save($quote, [['id' => $item->id, 'grupos' => $groups]])->assertStatus(422);
        $this->save($quote, [['id' => $item->id, 'grupos' => $this->config(-2)]], false)->assertStatus(422);
        $this->assertSame(0, Personalizacion::count());
    }

    public function test_name_counts_are_aggregated_by_size_when_colors_share_a_size(): void
    {
        $quote = $this->quote(); $item = $this->item($quote);
        $groups = $this->config(1);
        $groups[0]['tallas'][] = ['genero' => 'Unisex', 'color' => 'Rojo', 'talla' => 'M', 'cantidad' => 1];
        $groups[0]['matrices'][0]['tipo_contenido'] = 'texto_variable';
        $groups[0]['variables'] = [['talla' => 'M', 'valor_variable' => 'Ana'], ['talla' => 'M', 'valor_variable' => 'Luis']];
        $this->save($quote, [['id' => $item->id, 'grupos' => $groups]])->assertOk();
    }

    public function test_configuring_requires_sales_status_and_authorized_module(): void
    {
        $quote = $this->quote(); $item = $this->item($quote);
        $payload = [['id' => $item->id, 'grupos' => $this->config()]];
        $this->actingAsUser(['role' => 'bodega', 'modulos' => ['Inventario']]);
        $this->save($quote, $payload)->assertForbidden();
        $this->getJson("/api/cotizacion-items/{$item->id}/personalizacion")->assertForbidden();
        $this->actingAsUser(['role' => 'ejecutivo', 'modulos' => ['Comercial']]);
        $this->save($quote, $payload, false)->assertOk();
        $quote->update(['estado_id' => \App\Models\Estado::where('nombre', 'Cotización Enviada')->firstOrFail()->id]);
        $this->save($quote, $payload)->assertStatus(422);
    }
}
