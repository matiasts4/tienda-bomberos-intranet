<?php

namespace Tests\Feature\Inventario;

use App\Http\Controllers\SkuController;
use App\Models\PteColor;
use App\Models\PteMovimiento;
use App\Models\PteSku;
use App\Models\PteSkuGenerico;
use App\Models\PteStock;
use App\Models\PteTalla;
use Tests\TestCase;

class InventarioExportTest extends TestCase
{
    /** @var array<string, PteTalla> */
    private array $tallas = [];

    private function talla(string $nombre, int $orden): PteTalla
    {
        return $this->tallas[$nombre] ??= PteTalla::create(['nombre' => $nombre, 'orden' => $orden, 'is_activo' => true]);
    }

    private function sku(string $sku, string $nombre, PteTalla $talla, PteColor $color, int $cantidad, int $reservado = 0, int $critico = 5, ?PteSkuGenerico $generico = null): PteSku
    {
        $producto = PteSku::factory()->create([
            'sku' => $sku,
            'nombre' => $nombre,
            'talla_id' => $talla->id,
            'color_id' => $color->id,
            'sku_generico_id' => $generico?->id,
        ]);
        PteStock::factory()->create(['sku' => $sku, 'cantidad' => $cantidad, 'reserved_stock' => $reservado, 'stock_critico' => $critico]);

        return $producto;
    }

    private function reporte(array $query = []): array
    {
        return $this->getJson('/api/inventario/reporte?' . http_build_query($query))->assertOk()->json();
    }

    public function test_nombre_sin_talla_cubre_los_formatos_reales(): void
    {
        $this->assertSame('Chaqueta Pilot Unisex Azul Marino', SkuController::nombreSinTalla('Chaqueta Pilot Unisex Azul Marino XXS', 'XXS'));
        $this->assertSame('Chaqueta Pilot Unisex Negro', SkuController::nombreSinTalla('Chaqueta Pilot Unisex Negro ESPECIAL', 'ESPECIAL'));
        $this->assertSame('Geologo Tactico Unisex Blanco / Negro', SkuController::nombreSinTalla('Geologo Tactico Unisex Blanco / Negro Ajustable 1 (XS a M)', 'Ajustable 1'));
        $this->assertSame('Pantalon Casual Gabardina Masculino', SkuController::nombreSinTalla('Pantalon Casual Gabardina Masculino Generico', 'GENERICO'));
        // La talla no aparece al final: se deja el nombre completo.
        $this->assertSame('Kit Brigada Azul', SkuController::nombreSinTalla('Kit Brigada Azul', 'GENERICO'));
        $this->assertSame('Quepi Spandex Unisex Azul Marino', SkuController::nombreSinTalla('Quepi Spandex Unisex Azul Marino', 'Unica'));
        // "S" no se corta dentro de otra palabra ("Unisex") ni la "M" de "MC".
        $this->assertSame('Polera Unisex', SkuController::nombreSinTalla('Polera Unisex', 'S'));
        $this->assertSame('Camisa Oxford MC', SkuController::nombreSinTalla('Camisa Oxford MC M', 'M'));
        $this->assertSame('Sin talla', SkuController::nombreSinTalla('Sin talla', null));
    }

    public function test_numero_talla_usa_dos_digitos_del_orden(): void
    {
        $this->assertSame('00_GENERICO', SkuController::numeroTalla('GENERICO', 0));
        $this->assertSame('02_XXS', SkuController::numeroTalla('XXS', 2));
        $this->assertSame('11_ESPECIAL', SkuController::numeroTalla('ESPECIAL', 11));
        $this->assertSame('13_Ajustable 2', SkuController::numeroTalla('Ajustable 2', 13));
        $this->assertSame('', SkuController::numeroTalla(null, null));
    }

    public function test_reporte_incluye_columnas_para_tablas_dinamicas(): void
    {
        $this->actingAsAdmin();
        $color = PteColor::factory()->create(['nombre' => 'Azul Marino']);
        $generico = PteSkuGenerico::create(['sku_base' => '111440002200000', 'nombre' => 'Chaqueta Pilot Unisex', 'activo' => true]);
        $this->sku('111440002203111', 'Chaqueta Pilot Unisex Azul Marino XXS', $this->talla('XXS', 2), $color, 3, 2, 5, $generico);

        $fila = $this->reporte()[0];

        $this->assertSame('Chaqueta Pilot Unisex Azul Marino', $fila['prd']);
        $this->assertSame('02_XXS', $fila['num_talla']);
        $this->assertSame('Chaqueta Pilot Unisex', $fila['plantilla']);
        $this->assertSame(3, $fila['cantidad_fisica']);
        $this->assertSame(2, $fila['reserved_stock']);
        $this->assertSame(5, $fila['fisico']);
    }

    public function test_reporte_respeta_filtros_de_talla_y_stock_como_la_pantalla(): void
    {
        $this->actingAsAdmin();
        $color = PteColor::factory()->create(['nombre' => 'Negro']);
        $m = $this->talla('M', 5);
        $l = $this->talla('L', 6);
        $this->sku('SKU-M-CRITICO00', 'Polera Negro M', $m, $color, 2);
        $this->sku('SKU-M-NORMAL000', 'Polera Negro M2', $m, $color, 50);
        $this->sku('SKU-L-CRITICO00', 'Polera Negro L', $l, $color, 1);

        $porTalla = array_column($this->reporte(['talla' => 'M']), 'sku');
        sort($porTalla);
        $this->assertSame(['SKU-M-CRITICO00', 'SKU-M-NORMAL000'], $porTalla);

        $this->assertSame(['SKU-M-CRITICO00'], array_column($this->reporte(['talla' => 'M', 'stock_filter' => 'criticos']), 'sku'));
    }

    public function test_reporte_ordena_por_producto_color_y_talla_por_defecto(): void
    {
        $this->actingAsAdmin();
        $azul = PteColor::factory()->create(['nombre' => 'Azul']);
        $negro = PteColor::factory()->create(['nombre' => 'Negro']);
        $generico = PteSkuGenerico::create(['sku_base' => 'CHAQ0000000000', 'nombre' => 'Chaqueta', 'activo' => true]);
        $this->sku('CHAQ-NEGRO-XL00', 'Chaqueta Negro XL', $this->talla('XL', 7), $negro, 1, 0, 5, $generico);
        $this->sku('CHAQ-AZUL-XL000', 'Chaqueta Azul XL', $this->talla('XL', 7), $azul, 1, 0, 5, $generico);
        $this->sku('CHAQ-AZUL-XS000', 'Chaqueta Azul XS', $this->talla('XS', 3), $azul, 1, 0, 5, $generico);

        $this->assertSame(
            ['CHAQ-AZUL-XS000', 'CHAQ-AZUL-XL000', 'CHAQ-NEGRO-XL00'],
            array_column($this->reporte(), 'sku')
        );

        $this->assertSame('CHAQ-AZUL-XS000', $this->reporte(['sort_by' => 'talla', 'order' => 'asc'])[0]['sku']);
    }

    public function test_importar_sin_columna_de_stock_critico_conserva_el_actual(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $color = PteColor::factory()->create();
        $this->sku('IMPORT-CRIT-001', 'Polera', $this->talla('M', 5), $color, 10, 0, 12);

        $this->postJson('/api/movimientos/importar', [
            'productos' => [['sku' => 'IMPORT-CRIT-001', 'cantidad' => 10, 'stock_critico' => null]],
        ])->assertOk();

        $this->assertSame(12, (int) PteStock::where('sku', 'IMPORT-CRIT-001')->value('stock_critico'));
        $this->assertSame(0, PteMovimiento::where('sku', 'IMPORT-CRIT-001')->count(), 'Sin cambios no debe registrar movimiento');

        $this->postJson('/api/movimientos/importar', [
            'productos' => [['sku' => 'IMPORT-CRIT-001', 'cantidad' => 7]],
        ])->assertOk();

        $stock = PteStock::where('sku', 'IMPORT-CRIT-001')->first();
        $this->assertSame(7, (int) $stock->cantidad);
        $this->assertSame(12, (int) $stock->stock_critico);
    }
}
