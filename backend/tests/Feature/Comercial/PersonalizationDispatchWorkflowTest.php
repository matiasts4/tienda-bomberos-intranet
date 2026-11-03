<?php

namespace Tests\Feature\Comercial;

use App\Models\{Cotizacion, DetalleCotizacion, Despacho, Estado, Personalizacion};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class PersonalizationDispatchWorkflowTest extends TestCase
{
    private function quote(): Cotizacion
    {
        $user = $this->actingAsUser(['role' => 'superadmin']);
        $sale = Estado::create(['nombre' => 'Ventas', 'scope' => 'cotizacion', 'orden' => 1]);
        foreach (['Por despachar', 'Preparando despacho', 'Saliendo de bodega', 'Entregado a agencia'] as $order => $name)
            Estado::create(['nombre' => $name, 'scope' => 'despacho', 'orden' => ($order + 1) * 10]);
        return Cotizacion::factory()->forUser($user)->withEstado($sale)->create(['cliente_id' => null]);
    }

    private function job(?Cotizacion $quote = null, array $extra = []): Personalizacion
    {
        return Personalizacion::create(array_merge(['cotizacion_id' => $quote?->id,
            'usuario_creador_id' => auth()->id(), 'numero_item' => 1, 'nombre_producto' => 'Blusa Oxford',
            'sku' => '111111111100000', 'cantidad' => 4, 'tipo' => 'bordado', 'estado' => 'pendiente_diseno',
            'configuracion' => ['grupos' => $this->designGroups(4)]], $extra));
    }

    private function designGroups(int $quantity, string $size = 'M'): array
    {
        return [['nombre_grupo' => 'Grupo A',
            'tallas' => [['talla' => $size, 'color' => 'Azul', 'genero' => 'Mujer', 'cantidad' => $quantity]],
            'matrices' => [['posicion' => 'Frente', 'tecnica' => 'Bordado', 'tipo_contenido' => 'texto_fijo', 'valor_fijo' => 'BOMBEROS']], 'variables' => []]];
    }

    private function move(Personalizacion $job, string $state, array $extra = [])
    {
        return $this->putJson("/api/personalizaciones/{$job->id}", ['estado' => $state] + $extra);
    }

    private function qc(Personalizacion $job, int $bad = 0, array $extra = [])
    {
        return $this->postJson("/api/personalizaciones/{$job->id}/control_calidad", ['defectQty' => $bad] + $extra);
    }

    private function finish(Personalizacion $job, ?string $first = null): void
    {
        $this->move($job, 'aprobacion')->assertOk();
        $this->move($job, 'taller_por_hacer', $first ? ['tipo' => $first] : [])->assertOk();
        $this->move($job, 'taller_en_proceso')->assertOk();
        $this->qc($job)->assertOk();
        $this->move($job, 'taller_completado')->assertOk();
        $this->move($job, 'completado')->assertOk();
    }

    private function dispatch(Cotizacion $quote, string $name, string $verb = 'patch')
    {
        $id = Estado::where('scope', 'despacho')->where('nombre', $name)->value('id');
        return $this->{$verb . 'Json'}("/api/despachos/cotizacion/{$quote->id}/estado", ['estado_id' => $id]);
    }

    public function test_commercial_mixed_quote_requires_both_workshops_and_all_products_before_dispatch(): void
    {
        $quote = $this->quote();
        $first = DetalleCotizacion::factory()->forCotizacion($quote)->create(['n_item' => 1, 'cantidad' => 4, 'tipo_personalizacion' => 'mixto']);
        $second = DetalleCotizacion::factory()->forCotizacion($quote)->create(['n_item' => 2, 'cantidad' => 4, 'tipo_personalizacion' => 'bordado']);
        $mixed = $this->designGroups(4);
        $mixed[0]['matrices'][] = array_replace($mixed[0]['matrices'][0], ['tecnica' => 'Estampado']);
        $this->putJson("/api/cotizaciones/{$quote->id}/personalizacion-configuracion", ['enviar' => true, 'items' => [
            ['id' => $first->id, 'grupos' => $mixed], ['id' => $second->id, 'grupos' => $this->designGroups(4)],
        ]])->assertOk();
        $one = Personalizacion::where('numero_item', 1)->firstOrFail();
        $two = Personalizacion::where('numero_item', 2)->firstOrFail();
        $this->dispatch($quote, 'Preparando despacho')->assertStatus(422);
        $this->finish($one, 'estampado');
        $this->assertSame('bordado', $one->fresh()->tipo);
        $this->assertSame('aprobacion', $one->fresh()->estado);
        $this->assertSame('estampado', $one->fresh()->proceso_previo);
        $this->assertSame(0, Despacho::count());
        $this->finish($one);
        $this->assertSame(0, Despacho::count());
        $this->finish($two);
        $this->assertSame(1, Despacho::count());
        $this->assertNull(Despacho::first()->cliente_id);
        $this->dispatch($quote, 'Preparando despacho')->assertOk();
        $advanced = Despacho::first()->estado_id;
        $this->move($one, 'completado')->assertOk();
        $this->assertSame($advanced, Despacho::first()->estado_id);
        $this->assertSame(1, Despacho::count());
    }

    public function test_cannot_skip_stages_or_clear_a_second_process_from_payload(): void
    {
        $quote = $this->quote();
        $job = $this->job($quote, ['tipo' => 'mixto', 'siguiente_proceso' => 'mixto_pendiente']);
        foreach (['completado', 'taller_en_proceso', 'control_calidad', 'desconocido'] as $state)
            $this->move($job, $state)->assertStatus(422);
        $this->move($job, 'aprobacion')->assertOk();
        $this->move($job, 'taller_por_hacer')->assertStatus(422);
        $this->move($job, 'taller_por_hacer', ['tipo' => 'bordado', 'siguiente_proceso' => null])->assertOk();
        $this->assertSame('estampado', $job->fresh()->siguiente_proceso);
        $this->move($job, 'taller_en_proceso')->assertOk();
        $this->move($job, 'control_calidad')->assertStatus(422);
        $this->qc($job)->assertOk();
        $this->qc($job)->assertStatus(409);
    }

    public function test_partial_and_nested_rework_conserve_units_variants_and_files_without_self_merge(): void
    {
        $quote = $this->quote();
        $job = $this->job($quote, ['estado' => 'taller_en_proceso']);
        $job->variantes()->create(['talla' => 'M', 'color' => 'Azul', 'genero' => 'Mujer', 'cantidad' => 4]);
        $job->archivos()->create(['tipo_archivo' => 'matriz_bordado', 'url_archivo' => '/storage/diseno.dst', 'confirmado_fisico' => true]);
        $res = $this->qc($job, 2, ['okConfig' => ['grupos' => $this->designGroups(2)], 'defectiveConfig' => ['grupos' => $this->designGroups(2)]])->assertOk();
        $clone = Personalizacion::findOrFail($res->json('clon.id'));
        $this->assertEquals(2, $job->fresh()->variantes()->sum('cantidad'));
        $this->assertEquals(2, $clone->variantes()->sum('cantidad'));
        $this->assertSame(1, $clone->archivos()->count());
        $this->move($job, 'control_calidad', ['notas' => 'Esperando reproceso'])->assertOk();
        $this->assertNotNull($job->fresh());
        $this->assertSame(4, (int) Personalizacion::sum('cantidad'));
        $this->move($job, 'taller_completado')->assertStatus(422);
        $again = $this->qc($clone, 1, ['okConfig' => ['grupos' => $this->designGroups(1)], 'defectiveConfig' => ['grupos' => $this->designGroups(1)]])->assertOk();
        $third = Personalizacion::findOrFail($again->json('clon.id'));
        $this->assertTrue($job->fresh()->bloqueado_qc);
        $this->qc($third)->assertOk()->assertJsonPath('auto_merged', true);
        $this->assertSame(1, Personalizacion::count());
        $this->assertSame(4, (int) $job->fresh()->cantidad);
        $this->assertEquals(4, $job->variantes()->sum('cantidad'));
        $this->assertFalse($job->fresh()->bloqueado_qc);
        $this->assertSame(1, $job->archivos()->count());
        $this->assertSame(3, Personalizacion::withTrashed()->count());
        $this->move($job, 'taller_completado')->assertOk();
    }

    public function test_invalid_split_and_qc_roll_back_without_creating_or_losing_units(): void
    {
        $this->quote();
        $job = $this->job(null, ['estado' => 'taller_en_proceso']);
        $this->postJson("/api/personalizaciones/{$job->id}/split", ['remainingQty' => 4, 'splitQty' => 3])->assertStatus(422);
        $this->qc($job, 2, ['okConfig' => ['grupos' => $this->designGroups(3)], 'defectiveConfig' => ['grupos' => $this->designGroups(2)]])->assertStatus(422);
        $this->qc($job, 5)->assertStatus(422);
        $this->assertSame(1, Personalizacion::count());
        $this->assertSame(4, (int) $job->fresh()->cantidad);
        $this->assertSame('taller_en_proceso', $job->fresh()->estado);
    }

    public function test_split_then_merge_preserves_product_data_and_does_not_merge_unrelated_manual_orders(): void
    {
        $quote = $this->quote();
        $job = $this->job($quote);
        $this->postJson("/api/personalizaciones/{$job->id}/split", ['remainingQty' => 3, 'splitQty' => 1,
            'remainingConfig' => ['grupos' => $this->designGroups(3)], 'splitConfig' => ['grupos' => $this->designGroups(1)]])->assertOk();
        $this->postJson("/api/personalizaciones/{$job->id}/merge")->assertOk();
        $this->assertSame(4, (int) $job->fresh()->cantidad);
        $this->assertEquals(4, $job->variantes()->sum('cantidad'));
        $manual = $this->job(null, ['numero_item' => null]);
        $other = $this->job(null, ['numero_item' => null, 'nombre_producto' => 'Otra empresa']);
        $this->postJson("/api/personalizaciones/{$manual->id}/merge")->assertStatus(422);
        $this->assertNotNull($other->fresh());
    }

    public function test_dispatch_routes_share_validation_history_order_and_voucher_handling(): void
    {
        Storage::fake('public');
        $quote = $this->quote();
        $sale = $quote->estado_id;
        foreach (['patch', 'post'] as $verb) {
            $this->{$verb . 'Json'}("/api/despachos/cotizacion/{$quote->id}/estado", ['estado_id' => $sale])->assertStatus(422);
        }
        $this->dispatch($quote, 'Entregado a agencia')->assertStatus(422);
        $this->assertSame(0, Despacho::count());
        $this->dispatch($quote, 'Preparando despacho', 'post')->assertOk();
        $this->dispatch($quote, 'Saliendo de bodega')->assertOk();
        $this->dispatch($quote, 'Entregado a agencia', 'post')->assertStatus(422);
        $state = Estado::where('nombre', 'Entregado a agencia')->value('id');
        $this->post("/api/despachos/cotizacion/{$quote->id}/estado", ['estado_id' => $state,
            'voucher_file' => UploadedFile::fake()->create('voucher.pdf', 4, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
        $this->assertStringStartsWith('/storage/vouchers_agencia/', Despacho::first()->voucher_url);
        $this->assertSame(3, DB::table('historial_etapas')->where('model_type', Despacho::class)->count());
        $this->dispatch($quote, 'Por despachar')->assertStatus(422);
    }

    public function test_module_permissions_are_enforced_by_backend(): void
    {
        $quote = $this->quote();
        $job = $this->job($quote);
        $this->actingAsUser(['role' => 'ejecutivo', 'modulos' => ['Comercial']]);
        $this->move($job, 'aprobacion')->assertForbidden();
        $this->qc($job)->assertForbidden();
        $this->dispatch($quote, 'Preparando despacho')->assertForbidden();
        $this->actingAsUser(['role' => 'personalizacion', 'modulos' => ['Personalización']]);
        $this->move($job, 'aprobacion')->assertOk();
        $this->dispatch($quote, 'Preparando despacho')->assertForbidden();
    }

    public function test_cannot_delete_production_or_reset_a_mixed_second_stage(): void
    {
        $quote = $this->quote();
        $job = $this->job($quote, ['estado' => 'taller_en_proceso']);
        $this->deleteJson("/api/personalizaciones/{$job->id}")->assertStatus(422);
        $this->deleteJson("/api/personalizaciones/cotizacion/{$quote->id}")->assertStatus(409);
        $job->update(['estado' => 'aprobacion', 'proceso_previo' => 'estampado']);
        $this->deleteJson("/api/personalizaciones/cotizacion/{$quote->id}")->assertStatus(409);
        $this->assertNotNull($job->fresh());
    }

    public function test_reset_draft_is_atomic_and_clears_both_commercial_flags(): void
    {
        $quote = $this->quote();
        $quote->update(['estado_personalizacion' => 'sent_to_personalization', 'personalizacion_completada' => true]);
        $this->job($quote);
        $this->deleteJson("/api/personalizaciones/cotizacion/{$quote->id}")->assertOk();
        $this->assertSame(0, Personalizacion::count());
        $this->assertSame(1, Personalizacion::withTrashed()->count());
        $this->assertNull($quote->fresh()->estado_personalizacion);
        $this->assertFalse((bool) $quote->fresh()->personalizacion_completada);
    }

    public function test_named_partial_split_is_rejected_without_duplicating_names(): void
    {
        $this->quote();
        $groups = $this->designGroups(4);
        $groups[0]['variables'] = array_map(fn ($name) => ['talla' => 'M', 'valor_variable' => $name], ['Ana', 'Luis', 'Eva', 'José']);
        $job = $this->job(null, ['configuracion' => ['grupos' => $groups], 'estado' => 'taller_en_proceso']);
        $groups[0]['tallas'][0]['cantidad'] = 2;
        $this->qc($job, 2, ['okConfig' => ['grupos' => $groups], 'defectiveConfig' => ['grupos' => $groups]])
            ->assertStatus(422)->assertJsonFragment(['message' => 'Este grupo tiene nombres individuales. Divide grupos completos; una división parcial necesita identificar qué nombres corresponden a cada unidad.']);
        $this->assertSame(1, Personalizacion::count());
        $this->assertCount(4, $job->fresh()->configuracion['grupos'][0]['variables']);
    }

    public function test_full_rejection_counts_a_cycle_and_does_not_create_extra_cards(): void
    {
        $this->quote();
        $job = $this->job(null, ['estado' => 'taller_en_proceso']);
        $this->qc($job, 4)->assertOk();
        $this->assertSame(1, $job->fresh()->ciclos_qc);
        $this->assertSame(4, (int) $job->fresh()->cantidad);
        $this->assertSame(1, Personalizacion::count());
        $this->assertSame('taller_en_proceso', $job->fresh()->estado);
    }

    public function test_slim_design_update_preserves_commercial_groups_and_rejects_changed_totals(): void
    {
        $quote = $this->quote();
        $job = $this->job($quote);
        $this->putJson("/api/personalizaciones/{$job->id}", ['configuracion' => ['layers' => []]])->assertOk();
        $this->assertSame(4, $job->fresh()->configuracion['grupos'][0]['tallas'][0]['cantidad']);
        $this->putJson("/api/personalizaciones/{$job->id}", ['configuracion' => ['grupos' => []]])->assertStatus(422);
        $this->putJson("/api/personalizaciones/{$job->id}", ['configuracion' => ['grupos' => $this->designGroups(9)]])->assertStatus(422);
        $this->putJson("/api/personalizaciones/{$job->id}", ['configuracion' => ['grupos' => 'invalid']])->assertStatus(422);
    }

    public function test_manual_mixed_job_uses_canonical_workshop_types_and_preserves_sizes(): void
    {
        $this->quote();
        $response = $this->postJson('/api/personalizaciones/manual', ['cliente_nombre' => 'Cuartel', 'sku' => 'CUSTOM',
            'producto_nombre' => 'Polera', 'cantidad' => 4, 'talla' => 'M', 'color' => 'Azul', 'tipo' => 'mixed',
            'matrices' => $this->designGroups(4)[0]['matrices']])->assertCreated();
        $job = Personalizacion::findOrFail($response->json('id'));
        $this->assertSame('mixto', $job->tipo);
        $this->assertSame('mixto_pendiente', $job->siguiente_proceso);
        $this->assertSame(4, $job->configuracion['grupos'][0]['tallas'][0]['cantidad']);
        $this->finish($job, 'bordado');
        $this->assertSame('estampado', $job->fresh()->tipo);
    }

    public function test_unconfigured_quote_and_cancelled_sale_cannot_reach_dispatch_or_workshop(): void
    {
        $quote = $this->quote();
        DetalleCotizacion::factory()->forCotizacion($quote)->create(['tipo_personalizacion' => null, 'is_personalizable' => true]);
        $this->dispatch($quote, 'Preparando despacho')->assertStatus(422);
        $job = $this->job($quote, ['estado' => 'aprobacion']);
        $cancelled = Estado::create(['nombre' => 'Anulado', 'scope' => 'cotizacion']);
        $quote->update(['estado_id' => $cancelled->id]);
        $this->move($job, 'taller_por_hacer')->assertStatus(422);
        $this->dispatch($quote, 'Preparando despacho')->assertStatus(422);
    }

    public function test_kanban_exposes_dispatch_blocker_without_creating_dispatch_records(): void
    {
        $quote = $this->quote();
        $this->job($quote);
        $this->getJson('/api/despachos/kanban')->assertOk()
            ->assertJsonPath('items.0.bloqueo_despacho', 'Faltan trabajos de personalización o reprocesos por terminar.');
        $this->assertSame(0, Despacho::count());
    }

    public function test_workshop_pagination_can_reach_jobs_beyond_the_first_page(): void
    {
        $this->quote();
        foreach (range(1, 3) as $i) $this->job(null, ['nombre_producto' => "Producto $i", 'estado' => 'taller_por_hacer']);
        $this->getJson('/api/personalizaciones?proceso_tipo=bordado&per_page=2&page=1')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('last_page', 2);
        $this->getJson('/api/personalizaciones?proceso_tipo=bordado&per_page=2&page=2')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_mixed_historical_dispatch_states_do_not_break_the_current_shipping_path(): void
    {
        $quote = $this->quote();
        foreach (['Listo para despacho', 'En tránsito', 'Entregado', 'Anulado / Devuelto'] as $i => $name)
            Estado::create(['nombre' => $name, 'scope' => 'despacho', 'orden' => $i + 2]);
        $this->dispatch($quote, 'Preparando despacho')->assertOk();
        $this->dispatch($quote, 'Saliendo de bodega')->assertOk();
        $this->dispatch($quote, 'Anulado / Devuelto')->assertStatus(422);
    }
}
