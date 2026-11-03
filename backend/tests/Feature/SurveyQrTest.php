<?php

namespace Tests\Feature;

use App\Models\EncuestaQr;
use App\Models\EncuestaSatisfaccion;
use App\Models\User;
use App\Support\SurveyReference;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use SimpleSoftwareIO\QrCode\Generator;

// Base aislada: nunca ejecuta las migraciones globales ni abre una BD persistente.
class SurveyQrTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'session.driver' => 'array']);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('role')->default('ejecutivo');
        });
        Schema::create('clientes', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('nombre_empresa');
            $t->string('nombre_contacto')->nullable();
        });
        Schema::create('contactos', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
            $t->string('cliente_id')->nullable();
            $t->string('cargo')->nullable();
        });
        Schema::create('cuerpos', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('nombre');
        });
        Schema::create('companias', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('nombre');
            $t->string('cuerpo_id')->nullable();
        });
        Schema::create('cuerpo_contacto', function (Blueprint $t) {
            $t->integer('contacto_id');
            $t->string('cuerpo_id');
        });
        Schema::create('compania_contacto', function (Blueprint $t) {
            $t->integer('contacto_id');
            $t->string('compania_id');
        });
        Schema::create('cotizaciones', function (Blueprint $t) {
            $t->id();
            $t->string('numero')->nullable();
            $t->string('nombre');
            $t->string('cliente_id')->nullable();
            $t->integer('contacto_id')->nullable();
            $t->integer('user_id')->nullable();
            $t->string('cuerpo_id')->nullable();
            $t->string('compania_id')->nullable();
        });
        Schema::create('peticiones', function (Blueprint $t) {
            $t->id();
            $t->integer('cotizacion_id')->nullable();
            $t->string('referencia_externa')->nullable();
            $t->string('nombre_cotizacion')->nullable();
            $t->string('cliente_externo')->nullable();
            $t->integer('user_id')->nullable();
        });
        Schema::create('encuesta_satisfaccions', function (Blueprint $t) {
            $t->id();
            $t->integer('cotizacion_id')->nullable();
            $t->string('cliente_id')->nullable();
            $t->string('identificador_venta', 100)->nullable();
            $t->integer('vendedor_id')->nullable();
            $t->string('vendedor_manual', 100)->nullable();
            $t->string('nombre_cliente_manual', 100)->nullable();
            $t->string('nombre_venta')->nullable();
            foreach (['calidad_producto', 'calidad_personalizacion', 'tiempo_entrega', 'atencion_comercial'] as $field) {
                $t->integer($field);
            }
            $t->text('comentarios')->nullable();
            $t->timestamps();
        });
        // Este caso deja una respuesta histórica ANTES de ejecutar los alters aditivos.
        if ($this->name() === 'test_additive_migrations_preserve_legacy_response') {
            DB::table('encuesta_satisfaccions')->insert([
                'id' => 91, 'identificador_venta' => 'HIST-91', 'nombre_cliente_manual' => 'Cliente histórico',
                'vendedor_manual' => 'Vendedor histórico', 'nombre_venta' => 'Venta histórica',
                'calidad_producto' => 5, 'calidad_personalizacion' => 4, 'tiempo_entrega' => 3, 'atencion_comercial' => 5,
            ]);
        }
        (require database_path('migrations/2026_10_19_120000_link_surveys_to_quote_sources.php'))->up();
        (require database_path('migrations/2026_10_19_121000_create_encuesta_qrs_table.php'))->up();
        DB::table('users')->insert([['id' => 1, 'name' => 'Vendedor A'], ['id' => 2, 'name' => 'Vendedor B']]);
        DB::table('clientes')->insert(['id' => 'CLI1', 'nombre_empresa' => 'Empresa Uno']);
        DB::table('contactos')->insert(['id' => 1, 'nombre' => 'Contacto Calificado', 'cliente_id' => 'CLI1']);
        DB::table('cotizaciones')->insert(['id' => 1, 'nombre' => 'Uniformes del sistema', 'cliente_id' => 'CLI1', 'contacto_id' => 1, 'user_id' => 1]);
        foreach ([[1, 1, 'Cliente A'], [2, 1, 'Cliente A'], [3, 2, 'Cliente A'], [4, 1, 'Cliente B']] as [$id, $user, $client]) {
            DB::table('peticiones')->insert(['id' => $id, 'referencia_externa' => '1', 'nombre_cotizacion' => 'Uniformes externos', 'user_id' => $user, 'cliente_externo' => $client]);
        }
    }

    private function auth(): void
    {
        Sanctum::actingAs(User::findOrFail(1));
    }

    private function ratings(string $hash): array
    {
        return ['hash' => $hash, 'calidad_producto' => 5, 'calidad_personalizacion' => 4, 'tiempo_entrega' => 3, 'atencion_comercial' => 5];
    }

    private function reference(string $source, int $id, array $extra = []): string
    {
        return SurveyReference::encode(['source' => $source, 'source_id' => $id] + $extra);
    }

    public function test_generation_and_selectors_require_a_session(): void
    {
        $this->getJson('/api/encuestas/cotizaciones?source=sistema')->assertUnauthorized();
        $this->postJson('/api/encuestas/qr-pdf', ['source' => 'sistema', 'source_id' => 1])->assertUnauthorized();
        $this->getJson('/api/encuestas/manual-qr-pdf')->assertUnauthorized();
    }

    public function test_external_groups_are_separate_by_vendor_and_client_and_never_match_internal_digits(): void
    {
        $this->auth();
        $r = $this->getJson('/api/encuestas/cotizaciones?source=externa')->assertOk()->assertJsonPath('total', 3);
        $this->assertEqualsCanonicalizing([1, 3, 4], array_column($r->json('data'), 'source_id'));
        foreach ($r->json('data') as $row) {
            $this->assertNull($row['cotizacion_id']);
        }
        $this->getJson('/api/encuestas/cotizaciones?source=sistema&q=Uniformes')->assertOk()->assertJsonPath('data.0.cotizacion_id', 1);
        $this->getJson('/api/encuestas/cotizaciones?source=externa&q=Vendedor%20B')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_contact_directory_does_not_depend_on_prospecting_status_tables(): void
    {
        $this->auth();
        $this->getJson('/api/encuestas/contactos?q=Calificado')->assertOk()->assertJsonPath('data.0.id', 1)->assertJsonPath('data.0.empresa', 'Empresa Uno');
    }

    public function test_two_lines_of_an_external_quote_allow_one_response_but_other_vendor_and_client_are_independent(): void
    {
        $this->postJson('/api/encuestas', $this->ratings($this->reference('externa', 2, ['contacto_id' => 1, 'empresa_cliente' => 'Cliente A'])))->assertCreated();
        $row = EncuestaSatisfaccion::firstOrFail();
        $this->assertSame(1, $row->peticion_id);
        $this->assertNull($row->cotizacion_id);
        $this->assertSame(1, $row->contacto_id);
        $this->assertSame('CLI1', $row->cliente_id);
        $this->assertSame('Cliente A', $row->empresa_cliente);
        $this->postJson('/api/encuestas', $this->ratings($this->reference('externa', 1)))->assertConflict();
        $this->postJson('/api/encuestas', $this->ratings($this->reference('externa', 3)))->assertCreated();
        $this->postJson('/api/encuestas', $this->ratings($this->reference('externa', 4)))->assertCreated();
        $this->postJson('/api/encuestas', $this->ratings($this->reference('sistema', 1)))->assertCreated();
        $this->assertSame(4, EncuestaSatisfaccion::count());
    }

    public function test_old_internal_links_and_new_links_share_duplicate_detection(): void
    {
        $this->postJson('/api/encuestas', $this->ratings(base64_encode('1')))->assertCreated();
        $this->assertSame(1, EncuestaSatisfaccion::first()->cotizacion_id);
        $this->postJson('/api/encuestas', $this->ratings($this->reference('sistema', 1)))->assertConflict();
    }

    public function test_legacy_manual_remains_unlinked_and_does_not_block_a_new_external_with_the_same_number(): void
    {
        $legacy = base64_encode(json_encode(['type' => 'manual', 'identificador_venta' => '1', 'vendedor_id' => 1]));
        $this->postJson('/api/encuestas', $this->ratings($legacy))->assertCreated();
        $this->assertNull(EncuestaSatisfaccion::first()->cotizacion_id);
        $this->postJson('/api/encuestas', $this->ratings($legacy))->assertConflict();
        $this->postJson('/api/encuestas', $this->ratings($this->reference('externa', 1)))->assertCreated();
    }

    public function test_invalid_or_tampered_reference_and_ratings_never_write(): void
    {
        $valid = $this->reference('externa', 1);
        foreach (['%%%invalid', 'v2.bad', substr($valid, 0, -3).'xxx'] as $hash) {
            $this->postJson('/api/encuestas', $this->ratings($hash))->assertUnprocessable();
        }
        $this->postJson('/api/encuestas', array_replace($this->ratings($valid), ['calidad_producto' => 8]))->assertUnprocessable();
        $this->assertSame(0, EncuestaSatisfaccion::count());
    }

    public function test_pdf_uses_authoritative_quote_fields_keeps_contact_id_and_does_not_write(): void
    {
        $this->auth();
        $url = null;
        $viewData = null;
        $generator = \Mockery::mock(Generator::class);
        $generator->shouldReceive('format')->with('svg')->once()->andReturnSelf();
        $generator->shouldReceive('size')->with(300)->once()->andReturnSelf();
        $generator->shouldReceive('generate')->once()->withArgs(function ($value) use (&$url) {
            $url = $value;

            return true;
        })->andReturn('<svg/>');
        $this->app->instance(Generator::class, $generator);
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('output')->once()->andReturn('%PDF-test');
        Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $data) use (&$viewData) {
            $viewData = $data;

            return $view === 'pdf.survey_qr';
        })->andReturn($pdf);
        $this->postJson('/api/encuestas/qr-pdf', ['source' => 'externa', 'source_id' => 2, 'contacto_id' => 1, 'vendedor_id' => 2, 'nombre_venta' => 'Falso'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $payload = SurveyReference::decode($query['ref']);
        $this->assertSame(1, $payload['source_id']);
        $this->assertSame(1, $payload['contacto_id']);
        $this->assertSame('Uniformes externos', $payload['nombre_venta']);
        $this->assertSame('Cliente A', $payload['empresa_cliente']);
        $this->assertSame('Vendedor A', $viewData['source']['vendedor_nombre']);
        $this->assertSame(0, EncuestaSatisfaccion::count());
        $this->postJson('/api/encuestas', $this->ratings($query['ref']))->assertCreated();
        $this->assertSame('Contacto Calificado', EncuestaSatisfaccion::first()->nombre_cliente_manual);
    }

    public function test_explicit_manual_contact_does_not_restore_original_contact(): void
    {
        $this->postJson('/api/encuestas', $this->ratings($this->reference('sistema', 1, ['contacto_id' => null, 'cliente_nombre' => 'Persona Manual'])))->assertCreated();
        $this->assertNull(EncuestaSatisfaccion::first()->contacto_id);
        $this->assertSame('CLI1', EncuestaSatisfaccion::first()->cliente_id);
    }

    public function test_pdf_html_escapes_customer_input(): void
    {
        $html = view('pdf.survey_qr', ['source' => ['source' => 'externa', 'source_id' => 1, 'referencia' => '<script>', 'vendedor_nombre' => 'A&B'], 'details' => ['cliente_nombre' => '<img src=x>', 'empresa_cliente' => 'Empresa', 'nombre_venta' => '<b>Venta</b>'], 'qrImage' => 'data:image/svg+xml;base64,PHN2Zz4='])->render();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;img src=x&gt;', $html);
        $this->assertStringContainsString('A&amp;B', $html);
    }

    public function test_real_pdf_generation_returns_pdf_bytes_without_creating_a_survey(): void
    {
        $this->auth();
        $response = $this->postJson('/api/encuestas/qr-pdf', ['source' => 'sistema', 'source_id' => 1, 'contacto_id' => 1]);
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertGreaterThan(1000, strlen($response->getContent()));
        $this->assertSame(0, EncuestaSatisfaccion::count());
    }

    public function test_concurrent_unique_conflict_is_reported_as_already_answered(): void
    {
        $hash = $this->reference('externa', 1);
        DB::partialMock()->shouldReceive('transaction')->once()->andThrow(new UniqueConstraintViolationException('sqlite', 'insert into encuesta_satisfaccions', [], new \PDOException('duplicate key')));
        $this->postJson('/api/encuestas', $this->ratings($hash))->assertConflict()->assertJsonPath('message', 'Esta cotización ya fue evaluada. ¡Muchas gracias!');
    }

    public function test_history_tracks_all_prints_as_answered_and_reprinting_does_not_create_more_records(): void
    {
        $this->auth();
        foreach ([1, 2] as $line) {
            $this->postJson('/api/encuestas/qr-pdf', ['source' => 'externa', 'source_id' => $line])->assertOk();
        }
        $this->assertSame(2, EncuestaQr::count());
        $pending = $this->getJson('/api/encuestas/qrs?status=pendiente')->assertOk()->assertJsonPath('total', 2);
        $this->assertArrayNotHasKey('token', $pending->json('data.0'));
        $this->assertSame('Vendedor A', $pending->json('data.0.generado_por.name'));
        $qr = EncuestaQr::firstOrFail();
        $this->postJson('/api/encuestas', $this->ratings($qr->token))->assertCreated();
        $this->assertSame(2, EncuestaQr::whereNotNull('respondida_at')->count());
        $this->getJson('/api/encuestas/qrs?status=pendiente')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/encuestas/qrs?status=respondida&q=Uniformes')->assertOk()->assertJsonPath('total', 2);
        $this->getJson('/api/encuestas/qrs/'.$qr->id.'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(2, EncuestaQr::count());
        // Una nueva impresión después de responder no reaparece como pendiente.
        $this->postJson('/api/encuestas/qr-pdf', ['source' => 'externa', 'source_id' => 1])->assertOk();
        $this->assertSame(3, EncuestaQr::whereNotNull('respondida_at')->count());
        $this->assertSame(1, EncuestaSatisfaccion::count());
    }

    public function test_failed_pdf_render_does_not_leave_a_pending_history_record(): void
    {
        $this->auth();
        Pdf::shouldReceive('loadView')->once()->andThrow(new \RuntimeException('Fallo PDF simulado'));
        $this->postJson('/api/encuestas/qr-pdf', ['source' => 'sistema', 'source_id' => 1])->assertStatus(500);
        $this->assertSame(0, EncuestaQr::count());
        $this->assertSame(0, EncuestaSatisfaccion::count());
    }

    public function test_generated_qr_keeps_the_printed_seller_after_quote_transfer(): void
    {
        $this->auth();
        $this->postJson('/api/encuestas/qr-pdf', ['source' => 'sistema', 'source_id' => 1])->assertOk();
        $qr = EncuestaQr::firstOrFail();
        DB::table('cotizaciones')->where('id', 1)->update(['user_id' => 2]);
        $this->postJson('/api/encuestas', $this->ratings($qr->token))->assertCreated();
        $this->assertSame(1, EncuestaSatisfaccion::firstOrFail()->vendedor_id);
        $this->assertSame('Vendedor A', EncuestaSatisfaccion::firstOrFail()->vendedor_nombre);
    }

    public function test_additive_migrations_preserve_legacy_response(): void
    {
        $row = EncuestaSatisfaccion::findOrFail(91);
        $this->assertSame('HIST-91', $row->identificador_venta);
        $this->assertSame('Cliente histórico', $row->nombre_cliente_manual);
        $this->assertSame('Vendedor histórico', $row->vendedor_manual);
        $this->assertSame('Venta histórica', $row->nombre_venta);
        $this->assertSame(5, $row->calidad_producto);
        $this->assertNull($row->referencia_clave);
        $this->assertNull($row->peticion_id);
        $this->assertSame(1, EncuestaSatisfaccion::count());
        $this->assertSame(0, EncuestaQr::count());
    }
}
