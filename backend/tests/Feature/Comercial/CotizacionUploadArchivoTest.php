<?php

namespace Tests\Feature\Comercial;

use App\Models\Cotizacion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CotizacionUploadArchivoTest extends TestCase
{
    public function test_sube_archivo_y_persiste_metadata(): void
    {
        Storage::fake('public');
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create();

        $file = UploadedFile::fake()->create('orden.pdf', 200, 'application/pdf');

        $response = $this->postJson("/api/cotizaciones/{$cot->id}/archivos", [
            'archivo' => $file,
            'tipo' => 'orden_venta',
        ])->assertCreated();

        $this->assertDatabaseHas('cotizacion_archivos', [
            'cotizacion_id' => $cot->id,
            'tipo' => 'orden_venta',
            'nombre_archivo' => 'orden.pdf',
        ]);

        // El archivo se guardó en disk public bajo cotizaciones/{id}/...
        $files = Storage::disk('public')->allFiles("cotizaciones/{$cot->id}");
        $this->assertCount(1, $files);

        $response->assertJsonPath('tipo', 'orden_venta');
    }

    public function test_rechaza_tipo_invalido(): void
    {
        Storage::fake('public');
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create();

        $this->postJson("/api/cotizaciones/{$cot->id}/archivos", [
            'archivo' => UploadedFile::fake()->create('x.pdf', 10),
            'tipo' => 'tipo_no_valido',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['tipo']);
    }

    public function test_requiere_archivo(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create();

        $this->postJson("/api/cotizaciones/{$cot->id}/archivos", [
            'tipo' => 'pago',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['archivo']);
    }

    public function test_rechaza_archivos_mayores_a_10mb(): void
    {
        Storage::fake('public');
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create();

        // 11 MB
        $file = UploadedFile::fake()->create('grande.pdf', 11 * 1024);

        $this->postJson("/api/cotizaciones/{$cot->id}/archivos", [
            'archivo' => $file,
            'tipo' => 'pago',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['archivo']);
    }

    public function test_boucher_despacho_solo_en_cotizacion_finalizada(): void
    {
        Storage::fake('public');
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create();

        $this->postJson("/api/cotizaciones/{$cot->id}/archivos", [
            'archivo' => UploadedFile::fake()->create('boucher.pdf', 50),
            'tipo' => 'boucher_despacho',
        ])->assertStatus(422)->assertJsonValidationErrors(['tipo']);

        $cot->forceFill(['motivo_no_vigente' => 'finalizada'])->save();

        $this->postJson("/api/cotizaciones/{$cot->id}/archivos", [
            'archivo' => UploadedFile::fake()->create('boucher.pdf', 50),
            'tipo' => 'boucher_despacho',
        ])->assertCreated()->assertJsonPath('tipo', 'boucher_despacho');
    }
}
