<?php

namespace Tests\Feature\Comercial;

use App\Models\Cotizacion;
use App\Models\DetalleCotizacion;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CotizacionItemPersonalizacionTest extends TestCase
{
    private function makeItem(): DetalleCotizacion
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsUser(['role' => 'superadmin']);
        $cot = Cotizacion::factory()->forUser($admin)->withEstado(\App\Models\Estado::where('nombre', 'Aprobada por Cliente')->firstOrFail())->create();

        return DetalleCotizacion::factory()->forCotizacion($cot)->create([
            'tipo_personalizacion' => 'embroidery',
        ]);
    }

    public function test_show_devuelve_estructura_vacia_inicial(): void
    {
        $item = $this->makeItem();

        $this->getJson("/api/cotizacion-items/{$item->id}/personalizacion")
            ->assertOk()
            ->assertJsonPath('tipo_personalizacion', 'embroidery')
            ->assertJsonPath('grupos', []);
    }

    public function test_update_crea_grupo_con_tallas_matrices_y_variables(): void
    {
        $item = $this->makeItem();

        $payload = [
            'tipo_personalizacion' => 'print',
            'grupos' => [
                [
                    'nombre_grupo' => 'Espalda',
                    'tallas' => [
                        ['genero' => 'M', 'color' => 'Rojo', 'talla' => 'L', 'cantidad' => 5],
                        ['genero' => 'F', 'color' => 'Negro', 'talla' => 'S', 'cantidad' => 3],
                    ],
                    'matrices' => [
                        ['posicion' => 'Pecho', 'tecnica' => 'Bordado', 'tipo_contenido' => 'logo', 'valor_fijo' => 'logo.png', 'color' => 'Full Color'],
                    ],
                    'variables' => [
                        ['talla' => 'L', 'valor_variable' => 'Juan'],
                    ],
                ],
            ],
        ];

        $this->putJson("/api/cotizacion-items/{$item->id}/personalizacion", $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Personalización guardada correctamente');

        $item->refresh();
        $this->assertSame('bordado', $item->tipo_personalizacion); // La técnica real se deriva de la matriz, no de una bandera contradictoria.

        // El controller almacena los grupos como JSON en configuracion
        $config = $item->configuracion;
        $this->assertCount(1, $config);
        $this->assertSame('Espalda', $config[0]['nombre_grupo']);
        $this->assertCount(2, $config[0]['tallas']);
        $this->assertSame('M', $config[0]['tallas'][0]['genero']);
    }

    public function test_update_reemplaza_grupos_anteriores_via_cascade(): void
    {
        $item = $this->makeItem();

        // Primer set
        $this->putJson("/api/cotizacion-items/{$item->id}/personalizacion", [
            'grupos' => [
                ['nombre_grupo' => 'A', 'tallas' => [['talla' => 'M', 'cantidad' => 1]]],
                ['nombre_grupo' => 'B', 'tallas' => [['talla' => 'L', 'cantidad' => 2]]],
            ],
        ])->assertOk();
        $item->refresh();
        $this->assertCount(2, $item->configuracion);

        // Segundo set deja solo 1 grupo
        $this->putJson("/api/cotizacion-items/{$item->id}/personalizacion", [
            'grupos' => [
                ['nombre_grupo' => 'C', 'tallas' => [['talla' => 'XL', 'cantidad' => 7]]],
            ],
        ])->assertOk();

        $item->refresh();
        $this->assertCount(1, $item->configuracion);
        $this->assertSame('C', $item->configuracion[0]['nombre_grupo']);
    }

    public function test_update_requiere_grupos(): void
    {
        $item = $this->makeItem();

        $this->putJson("/api/cotizacion-items/{$item->id}/personalizacion", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['grupos']);
    }

    public function test_show_devuelve_404_si_item_no_existe(): void
    {
        $this->seedEstadosCotizacion();
        $this->actingAsUser(['role' => 'superadmin']);

        $this->getJson('/api/cotizacion-items/99999/personalizacion')
            ->assertNotFound();
    }

    public function test_upload_matriz_image_guarda_y_devuelve_url(): void
    {
        Storage::fake('public');
        $item = $this->makeItem();

        $response = $this->postJson("/api/cotizacion-items/{$item->id}/personalizacion/upload", [
            'image' => UploadedFile::fake()->image('matriz.png', 200, 200),
        ])->assertCreated();

        $this->assertNotEmpty($response->json('url'));
        $files = Storage::disk('public')->allFiles("personalizaciones/item_{$item->id}");
        $this->assertCount(1, $files);
    }

    public function test_upload_matriz_image_rechaza_no_imagen(): void
    {
        Storage::fake('public');
        $item = $this->makeItem();

        $this->postJson("/api/cotizacion-items/{$item->id}/personalizacion/upload", [
            'image' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['image']);
    }

    public function test_upload_matriz_image_rechaza_mayor_a_5mb(): void
    {
        Storage::fake('public');
        $item = $this->makeItem();

        $this->postJson("/api/cotizacion-items/{$item->id}/personalizacion/upload", [
            'image' => UploadedFile::fake()->image('huge.png')->size(6 * 1024),
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['image']);
    }

    public function test_upload_names_csv_extrae_nombres(): void
    {
        Storage::fake('public');
        $item = $this->makeItem();

        $csv = "Nombre\nAna\nLuis\n   \nPedro";
        $file = UploadedFile::fake()->createWithContent('nombres.csv', $csv);

        $response = $this->postJson("/api/cotizacion-items/{$item->id}/personalizacion/upload-names", [
            'file' => $file,
        ])->assertCreated();

        $extraidos = $response->json('nombres_extraidos');
        $this->assertSame(['Ana', 'Luis', 'Pedro'], $extraidos);
    }

    public function test_upload_names_txt_extrae_lineas(): void
    {
        Storage::fake('public');
        $item = $this->makeItem();

        $file = UploadedFile::fake()->createWithContent(
            'nombres.txt',
            "Pedro\n\nMaría\nJuan\n"
        );

        $response = $this->postJson("/api/cotizacion-items/{$item->id}/personalizacion/upload-names", [
            'file' => $file,
        ])->assertCreated();

        $this->assertSame(['Pedro', 'María', 'Juan'], $response->json('nombres_extraidos'));
    }

    public function test_upload_names_rechaza_extension_no_permitida(): void
    {
        Storage::fake('public');
        $item = $this->makeItem();

        $this->postJson("/api/cotizacion-items/{$item->id}/personalizacion/upload-names", [
            'file' => UploadedFile::fake()->create('foo.png', 10, 'image/png'),
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['file']);
    }
}
