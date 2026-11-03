<?php

namespace Tests\Feature\Inventario;

use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\MovimientoController;
use App\Models\PteMovimiento;
use App\Models\PteSku;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BitacoraTest extends TestCase
{
    public function test_details_and_individual_pdf_keep_the_same_requester_partition_as_summary(): void
    {
        $operator = $this->actingAsEjecutivo();
        $sku = PteSku::factory()->create();
        $status = \App\Models\Estado::firstOrCreate(['scope' => 'peticion', 'nombre' => 'Pendiente']);
        foreach ([null, 'shared-historical-lot'] as $lot) {
            $ids = [];
            foreach ([1, 2] as $n) {
                $requester = \App\Models\User::factory()->create();
                $petition = \App\Models\Peticion::create(['sku' => $sku->sku, 'cantidad' => 1, 'user_id' => $requester->id, 'estado_id' => $status->id, 'fecha_creacion' => '2026-08-10']);
                $ids[] = PteMovimiento::factory()->paraSku($sku)->create([
                    'id_lote' => $lot, 'usuario_id' => $operator->id, 'peticion_id' => $petition->id,
                    'referencia_externa' => '123', 'fecha_hora' => '2026-08-10 10:00:00', 'tipo_movimiento' => 'SALIDA_VENTA',
                ])->id;
            }
            $summary = collect($this->getJson('/api/movimientos/general?mes=2026-08')->assertOk()->json('data'))->keyBy('movimiento_id');
            foreach ($ids as $id) {
                $this->assertSame(1, $summary[$id]['total_registros']);
                $this->getJson('/api/movimientos/lote-items?movimiento_id='.$id)->assertOk()->assertJsonPath('pagination.total', 1)->assertJsonPath('data.0.id', $id);
                $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
                $pdf->shouldReceive('stream')->once()->andReturn(response('%PDF-test', 200, ['Content-Type' => 'application/pdf']));
                Pdf::shouldReceive('loadView')->once()->with('pdf.bitacora', \Mockery::on(fn ($view) => $view['reporte'][0]['total_registros'] === 1 && $view['reporte'][0]['items']->pluck('sku')->all() === [$sku->sku]))->andReturn($pdf);
                $this->getJson('/api/movimientos/pdf/individual?movimiento_id='.$id)->assertOk();
            }
        }
    }

    public function test_user_options_cover_full_month_without_exposing_user_details(): void
    {
        $this->getJson('/api/movimientos/usuarios?mes=2026-08')->assertUnauthorized();
        $user = $this->actingAsEjecutivo(['name' => 'Ana']);
        $other = \App\Models\User::factory()->create(['name' => 'Bruno']);
        $outside = \App\Models\User::factory()->create(['name' => 'Fuera del mes']);
        $sku = PteSku::factory()->create();
        foreach ([[$user->id, '2026-08-01'], [$user->id, '2026-08-02'], [$other->id, '2026-08-31'], [null, '2026-08-20'], [$outside->id, '2026-09-01']] as [$id, $date]) {
            PteMovimiento::factory()->paraSku($sku)->create(['usuario_id' => $id, 'fecha_hora' => $date.' 12:00:00']);
        }
        $rows = $this->getJson('/api/movimientos/usuarios?mes=2026-08&semana=1&page=2&usuario_id='.$user->id)
            ->assertOk()->assertJsonCount(3, 'data')->json('data');
        $options = collect($rows)->keyBy('value');
        $this->assertSame('Ana', $options[(string) $user->id]['label']);
        $this->assertSame('Bruno', $options[(string) $other->id]['label']);
        $this->assertSame('Sistema / sin usuario', $options['sistema']['label']);
        foreach ($rows as $row) $this->assertSame(['value', 'label'], array_keys($row));
        $this->getJson('/api/movimientos/usuarios?mes=2026-07')->assertJsonCount(0, 'data');
        $this->getJson('/api/movimientos/usuarios?mes=incorrecto')->assertStatus(422);
    }

    public function test_user_filter_is_applied_before_pagination_and_combines_with_other_filters(): void
    {
        // Exercise validation repeatedly without exhausting the production PDF rate limit.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $user = $this->actingAsEjecutivo();
        $other = \App\Models\User::factory()->create();
        $sku = PteSku::factory()->create();
        PteMovimiento::factory()->paraSku($sku)->count(3)->create(['usuario_id' => $user->id, 'fecha_hora' => '2026-08-30 12:00:00']);
        PteMovimiento::factory()->paraSku($sku)->create(['usuario_id' => $other->id, 'fecha_hora' => '2026-08-30 12:00:00']);
        PteMovimiento::factory()->paraSku($sku)->create(['usuario_id' => null, 'fecha_hora' => '2026-08-30 12:00:00']);
        PteMovimiento::factory()->paraSku($sku)->create(['usuario_id' => $user->id, 'fecha_hora' => '2026-08-31 12:00:00']);
        $url = '/api/movimientos/general?mes=2026-08&semana=5&fecha=2026-08-30&tipo=INGRESO_BODEGA&per_page=1&usuario_id='.$user->id;
        $first = $this->getJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('pagination.total', 3)->assertJsonPath('pagination.last_page', 3);
        $second = $this->getJson($url.'&page=2')->assertOk();
        $this->assertNotSame($first->json('data.0.movimiento_id'), $second->json('data.0.movimiento_id'));
        $this->getJson('/api/movimientos/general?mes=2026-08&usuario_id=sistema')->assertOk()->assertJsonPath('pagination.total', 1)->assertJsonPath('data.0.usuario', 'Sistema');
        foreach (['0', '-1', 'abc', '1.5'] as $invalid) {
            $this->getJson('/api/movimientos/general?mes=2026-08&usuario_id='.$invalid)->assertStatus(422);
            $this->getJson('/api/movimientos/pdf/mensual?mes=2026-08&usuario_id='.$invalid)->assertStatus(422);
        }
    }

    public function test_monthly_and_weekly_pdfs_respect_user_filter(): void
    {
        $user = $this->actingAsEjecutivo();
        $other = \App\Models\User::factory()->create();
        $sku = PteSku::factory()->create();
        $included = PteMovimiento::factory()->paraSku($sku)->create(['usuario_id' => $user->id, 'fecha_hora' => '2026-08-30 12:00:00']);
        PteMovimiento::factory()->paraSku($sku)->create(['usuario_id' => $other->id, 'fecha_hora' => '2026-08-30 12:00:00']);
        $anonymous = PteMovimiento::factory()->paraSku($sku)->create(['usuario_id' => null, 'fecha_hora' => '2026-08-30 12:00:00']);
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->with('a4', 'landscape')->times(3)->andReturnSelf();
        $pdf->shouldReceive('download')->times(3)->andReturn(response('%PDF-test', 200, ['Content-Type' => 'application/pdf']));
        Pdf::shouldReceive('loadView')->twice()->with('pdf.bitacora_mensual', \Mockery::on(fn ($data) => $data['movimientos']->pluck('id')->all() === [$included->id]))->andReturn($pdf);
        Pdf::shouldReceive('loadView')->once()->with('pdf.bitacora_mensual', \Mockery::on(fn ($data) => $data['movimientos']->pluck('id')->all() === [$anonymous->id]))->andReturn($pdf);
        $this->getJson('/api/movimientos/pdf/mensual?mes=2026-08&usuario_id='.$user->id)->assertOk();
        $this->getJson('/api/movimientos/pdf/semanal?mes=2026-08&semana=5&usuario_id='.$user->id)->assertOk();
        $this->getJson('/api/movimientos/pdf/mensual?mes=2026-08&usuario_id=sistema')->assertOk();
    }

    public function test_month_filters_boundaries_pagination_and_summary_only(): void
    {
        $this->actingAsEjecutivo();
        $sku = PteSku::factory()->create();
        foreach (['2026-07-31 23:59:59', '2026-08-01 00:00:00', '2026-08-31 23:59:59', '2026-09-01 00:00:00'] as $date) {
            PteMovimiento::factory()->paraSku($sku)->create(['fecha_hora' => $date]);
        }
        $response = $this->getJson('/api/movimientos/general?mes=2026-08&per_page=1')->assertOk();
        $response->assertJsonPath('pagination.total', 2)->assertJsonPath('pagination.last_page', 2)
            ->assertJsonPath('data.0.fecha_hora_grupo', '31/08/2026 23:59')->assertJsonPath('data.0.items', null);
        $id = $response->json('data.0.movimiento_id');
        $next = $this->getJson('/api/movimientos/general?mes=2026-08&per_page=1&page=2')->assertOk();
        $this->assertNotEquals($id, $next->json('data.0.movimiento_id'));
        $this->getJson('/api/movimientos/general?mes=2026-08&fecha=2026-08-01')->assertJsonPath('pagination.total', 1);
        $this->getJson('/api/movimientos/general?mes=2026-08&tipo=RESERVA_PRODUCTO')->assertJsonPath('pagination.total', 0)->assertJsonPath('pagination.last_page', 1);
    }

    public function test_validation_and_authentication(): void
    {
        $this->getJson('/api/movimientos/general')->assertUnauthorized();
        $this->getJson('/api/movimientos/pdf/mensual?mes=2026-08')->assertUnauthorized();
        $this->getJson('/api/movimientos/pdf/individual?movimiento_id=1')->assertUnauthorized();
        $this->getJson('/api/movimientos/pdf')->assertStatus(410);
        $this->actingAsEjecutivo();
        foreach (['mes=2026-13', 'per_page=0', 'per_page=1000', 'page=-1', 'mes=2026-08&fecha=2026-07-01'] as $query) {
            $this->getJson('/api/movimientos/general?'.$query)->assertStatus(422);
        }
        $this->getJson('/api/movimientos/pdf/mensual')->assertStatus(422);
        $this->getJson('/api/movimientos/pdf/mensual?mes=2026-08&fecha=2026-08-01')->assertStatus(422);
        $this->getJson('/api/movimientos/lote-items')->assertStatus(422);
        $this->getJson('/api/movimientos/lote-items?movimiento_id=99999')->assertNotFound();
    }

    public function test_legacy_groups_are_isolated_and_details_are_paginated(): void
    {
        $this->actingAsUser(['role' => 'bodega']);
        $sku = PteSku::factory()->create();
        $items = PteMovimiento::factory()->paraSku($sku)->count(30)->create([
            'id_lote' => null, 'usuario_id' => null, 'fecha_hora' => '2026-08-01 10:00:15',
        ]);
        PteMovimiento::factory()->paraSku($sku)->create(['id_lote' => null, 'fecha_hora' => '2026-08-01 10:01:00']);
        PteMovimiento::factory()->paraSku($sku)->create(['id_lote' => null, 'fecha_hora' => '2026-08-01 10:00:15', 'tipo_movimiento' => 'SALIDA_VENTA']);
        PteMovimiento::factory()->paraSku($sku)->create(['id_lote' => 'real-lote', 'fecha_hora' => '2026-08-01 10:00:15']);
        $id = $items->first()->id;
        $this->getJson('/api/movimientos/general?mes=2026-08')->assertJsonPath('pagination.total', 4);
        $this->getJson("/api/movimientos/lote-items?movimiento_id=$id")->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('pagination.total', 30);
        $this->getJson("/api/movimientos/lote-items?movimiento_id=$id&page=2")->assertOk()->assertJsonCount(5, 'data');
    }

    public function test_summary_query_count_is_constant_for_fifty_operations(): void
    {
        $sku = PteSku::factory()->create();
        PteMovimiento::factory()->paraSku($sku)->count(50)->create(['fecha_hora' => '2026-08-01 10:00:00']);
        $request = Request::create('/', 'GET', ['mes' => '2026-08', 'per_page' => 50]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $old = (new MovimientoController())->historialGeneral($request);
        $oldCount = count(DB::getQueryLog());
        DB::flushQueryLog();
        $new = (new BitacoraController())->historialGeneral($request);
        $newCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame(2, $newCount);
        $this->assertGreaterThan($newCount, $oldCount);
        $this->assertCount(50, $new->getData(true)['data']);
        $this->assertLessThan(strlen($old->getContent()), strlen($new->getContent()));
        fwrite(STDOUT, "\nBitacora 50 operations: $oldCount -> $newCount queries; ".strlen($old->getContent()).' -> '.strlen($new->getContent())." bytes\n");
    }

    public function test_monthly_pdf_includes_only_month_and_type(): void
    {
        $this->actingAsEjecutivo();
        $sku = PteSku::factory()->create();
        $included = PteMovimiento::factory()->paraSku($sku)->create(['fecha_hora' => '2026-08-10 12:00:00']);
        PteMovimiento::factory()->paraSku($sku)->create(['fecha_hora' => '2026-07-10 12:00:00']);
        PteMovimiento::factory()->paraSku($sku)->salida()->create(['fecha_hora' => '2026-08-10 12:00:00']);
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->with('a4', 'landscape')->once()->andReturnSelf();
        $pdf->shouldReceive('download')->with('bitacora-2026-08.pdf')->once()->andReturn(response('%PDF-test', 200, ['Content-Type' => 'application/pdf']));
        Pdf::shouldReceive('loadView')->once()->with('pdf.bitacora_mensual', \Mockery::on(fn ($data) => $data['movimientos']->pluck('id')->all() === [$included->id]))->andReturn($pdf);
        $this->getJson('/api/movimientos/pdf/mensual?mes=2026-08&tipo=INGRESO_BODEGA')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_actual_monthly_pdf_and_empty_month(): void
    {
        $this->actingAsEjecutivo();
        $sku = PteSku::factory()->create();
        PteMovimiento::factory()->paraSku($sku)->create(['fecha_hora' => '2026-08-10 12:00:00']);
        $result = $this->getJson('/api/movimientos/pdf/mensual?mes=2026-08')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $result->getContent());
        $this->getJson('/api/movimientos/pdf/mensual?mes=2026-07')->assertNotFound();
    }

    public function test_individual_pdf_uses_exact_group_even_with_anonymous_user(): void
    {
        $this->actingAsEjecutivo();
        $sku = PteSku::factory()->create();
        $anchor = PteMovimiento::factory()->paraSku($sku)->create(['id_lote' => null, 'usuario_id' => null, 'fecha_hora' => '2026-08-10 12:00:00']);
        PteMovimiento::factory()->paraSku($sku)->create(['id_lote' => 'other-lot', 'fecha_hora' => '2026-08-10 12:00:00']);
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('stream')->once()->andReturn(response('%PDF-test', 200, ['Content-Type' => 'application/pdf']));
        Pdf::shouldReceive('loadView')->once()->with('pdf.bitacora', \Mockery::on(fn ($data) => $data['reporte'][0]['total_registros'] === 1))->andReturn($pdf);
        $this->getJson('/api/movimientos/pdf/individual?movimiento_id='.$anchor->id)->assertOk();
    }

    public function test_monthly_pdf_never_silently_truncates_large_month(): void
    {
        $this->actingAsEjecutivo();
        $sku = PteSku::factory()->create();
        $rows = array_fill(0, 3001, ['sku' => $sku->sku, 'tipo_movimiento' => 'INGRESO_BODEGA', 'cantidad' => 1, 'saldo_anterior' => 0, 'saldo_nuevo' => 1, 'fecha_hora' => '2026-08-10 12:00:00']);
        foreach (array_chunk($rows, 100) as $chunk) DB::table('pte_movimientos')->insert($chunk);
        Pdf::shouldReceive('loadView')->never();
        $this->getJson('/api/movimientos/pdf/mensual?mes=2026-08')->assertStatus(422);
    }

    public function test_calendar_weeks_are_clipped_to_month_and_day_must_match_week(): void
    {
        $this->actingAsEjecutivo();
        $sku = PteSku::factory()->create();
        foreach (['2026-07-31', '2026-08-01', '2026-08-02', '2026-08-03', '2026-08-30', '2026-08-31', '2026-09-01'] as $date) {
            PteMovimiento::factory()->paraSku($sku)->create(['fecha_hora' => $date.' 12:00:00']);
        }
        $this->getJson('/api/movimientos/general?mes=2026-08&semana=1')->assertOk()->assertJsonPath('pagination.total', 2);
        $this->getJson('/api/movimientos/general?mes=2026-08&semana=2')->assertOk()->assertJsonPath('pagination.total', 1);
        $this->getJson('/api/movimientos/general?mes=2026-08&semana=6')->assertOk()->assertJsonPath('pagination.total', 1)->assertJsonPath('data.0.fecha_hora_grupo', '31/08/2026 12:00');
        $this->getJson('/api/movimientos/general?mes=2026-08&semana=1&fecha=2026-08-03')->assertStatus(422);
        $this->getJson('/api/movimientos/general?mes=2026-02&semana=6')->assertStatus(422);
        $this->getJson('/api/movimientos/general?mes=2026-08&semana=7')->assertStatus(422);
    }

    public function test_weekly_pdf_uses_exact_week_not_full_month(): void
    {
        $this->actingAsEjecutivo();
        $sku = PteSku::factory()->create();
        $included = PteMovimiento::factory()->paraSku($sku)->create(['fecha_hora' => '2026-08-30 23:59:59']);
        PteMovimiento::factory()->paraSku($sku)->create(['fecha_hora' => '2026-08-31 00:00:00']);
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->with('a4', 'landscape')->once()->andReturnSelf();
        $pdf->shouldReceive('download')->with('bitacora-2026-08-semana-5.pdf')->once()->andReturn(response('%PDF-test', 200, ['Content-Type' => 'application/pdf']));
        Pdf::shouldReceive('loadView')->once()->with('pdf.bitacora_mensual', \Mockery::on(fn ($data) => $data['semana'] === 5 && $data['periodo'] === '24/08/2026 al 30/08/2026' && $data['movimientos']->pluck('id')->all() === [$included->id]))->andReturn($pdf);
        $this->getJson('/api/movimientos/pdf/semanal?mes=2026-08&semana=5')->assertOk();
        $this->getJson('/api/movimientos/pdf/semanal?mes=2026-08')->assertStatus(422);
    }

    public function test_client_is_taken_from_snapshot_or_linked_quote_not_guessed(): void
    {
        $this->actingAsEjecutivo();
        $sku = PteSku::factory()->create();
        $quote = \App\Models\Cotizacion::factory()->create();
        $quote->cliente->update(['nombre_empresa' => 'Cliente del sistema']);
        $internal = PteMovimiento::factory()->paraSku($sku)->create(['cotizacion_id' => $quote->id, 'fecha_hora' => '2026-08-30 12:00:00']);
        $external = PteMovimiento::factory()->paraSku($sku)->create(['cliente_nombre' => 'Cuerpo de Marchigüe', 'fecha_hora' => '2026-08-30 12:00:00']);
        $unknown = PteMovimiento::factory()->paraSku($sku)->create(['fecha_hora' => '2026-08-30 12:00:00']);
        $rows = collect($this->getJson('/api/movimientos/general?mes=2026-08')->assertOk()->json('data'))->keyBy('movimiento_id');
        $this->assertSame('Cliente del sistema', $rows[$internal->id]['cliente_nombre']);
        $this->assertSame('Cuerpo de Marchigüe', $rows[$external->id]['cliente_nombre']);
        $this->assertNull($rows[$unknown->id]['cliente_nombre']);
    }

    public function test_solicitante_is_resolved_and_requester_filter_works(): void
    {
        $this->actingAsEjecutivo();
        $executive = \App\Models\User::factory()->create(['name' => 'Camila Comercial']);
        $operator = \App\Models\User::factory()->create(['name' => 'Oscar Bodega']);
        $sku = PteSku::factory()->create();
        $quote = \App\Models\Cotizacion::factory()->create(['user_id' => $executive->id]);

        $movement = PteMovimiento::factory()->paraSku($sku)->create([
            'usuario_id' => $operator->id,
            'cotizacion_id' => $quote->id,
            'tipo_movimiento' => 'SALIDA_VENTA',
            'fecha_hora' => '2026-08-15 10:30:00',
        ]);

        // General endpoint returns both usuario (bodega) and solicitante (comercial)
        $res = $this->getJson('/api/movimientos/general?mes=2026-08')->assertOk();
        $item = collect($res->json('data'))->firstWhere('movimiento_id', $movement->id);
        $this->assertNotNull($item);
        $this->assertSame('Oscar Bodega', $item['usuario']);
        $this->assertSame('Camila Comercial', $item['solicitante']);

        // Users endpoint includes both the direct operator and the quotation requester
        $usersRes = $this->getJson('/api/movimientos/usuarios?mes=2026-08')->assertOk();
        $userValues = collect($usersRes->json('data'))->pluck('value')->all();
        $this->assertContains((string) $operator->id, $userValues);
        $this->assertContains((string) $executive->id, $userValues);

        // Filtering by the executive (solicitante) finds this movement
        $filterRes = $this->getJson('/api/movimientos/general?mes=2026-08&usuario_id='.$executive->id)->assertOk();
        $this->assertSame(1, $filterRes->json('pagination.total'));
        $this->assertSame($movement->id, $filterRes->json('data.0.movimiento_id'));
    }
}
