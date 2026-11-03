<?php

namespace Tests\Unit\Comercial;

use App\Http\Requests\CotizacionUpdateRequest;
use Tests\TestCase;

/**
 * Pruebas de la lógica de prepareForValidation y reglas custom
 * (sin tocar BD ni HTTP).
 */
class CotizacionUpdateRequestTest extends TestCase
{
    private function prepare(array $payload): array
    {
        $request = CotizacionUpdateRequest::create('/x', 'PATCH', $payload);

        // prepareForValidation es protected → invocamos via reflection
        $ref = new \ReflectionMethod($request, 'prepareForValidation');
        $ref->setAccessible(true);
        $ref->invoke($request);

        return $request->all();
    }

    public function test_normaliza_montos_chilenos_en_cabecera(): void
    {
        $out = $this->prepare([
            'total_neto' => '12.597',
            'iva' => '$ 2.393',
            'total' => '14,990',
        ]);

        $this->assertSame(12597, $out['total_neto']);
        $this->assertSame(2393, $out['iva']);
        $this->assertSame(14990, $out['total']);
    }

    public function test_normaliza_strings_vacios_a_null_en_detalles(): void
    {
        $out = $this->prepare([
            'detalles' => [
                ['sku' => '   ', 'producto' => 'Casco', 'cantidad' => '2', 'precio_unitario' => '1.000'],
            ],
        ]);

        $this->assertNull($out['detalles'][0]['sku']);
        $this->assertSame('Casco', $out['detalles'][0]['producto']);
        $this->assertSame(2, $out['detalles'][0]['cantidad']);
        $this->assertSame(1000, $out['detalles'][0]['precio_unitario']);
    }

    public function test_calcula_neto_exacto_por_linea_antes_de_redondear(): void
    {
        // precio_unitario con decimales (float) simula un precio con descuento
        // calculado en el frontend: cada línea, si se redondeara individualmente,
        // perdería o ganaría fracciones de peso. _total_neto_exacto debe conservar
        // el valor real (precio * cantidad) sin redondear, para que el backend
        // pueda sumar primero y redondear una sola vez al agregar el total.
        $out = $this->prepare([
            'detalles' => [
                ['producto' => 'Casco', 'cantidad' => 3, 'precio_unitario' => 8547.65],
                ['producto' => 'Guante', 'cantidad' => 2, 'precio_unitario' => 1999.4],
            ],
        ]);

        $this->assertEqualsWithDelta(3 * 8547.65, $out['detalles'][0]['_total_neto_exacto'], 0.001);
        $this->assertEqualsWithDelta(2 * 1999.4, $out['detalles'][1]['_total_neto_exacto'], 0.001);

        // precio_unitario per-línea sigue redondeándose a entero para persistir
        // (la columna en BD es entera), pero eso ya no es lo que se usa para
        // sumar el total de cabecera.
        $this->assertSame(8548, $out['detalles'][0]['precio_unitario']);
        $this->assertSame(1999, $out['detalles'][1]['precio_unitario']);
    }

    public function test_usa_total_neto_exacto_de_la_linea_cuando_viene_directo(): void
    {
        $out = $this->prepare([
            'detalles' => [
                ['producto' => 'Casco', 'cantidad' => 1, 'total_neto' => 4250.4],
            ],
        ]);

        $this->assertEqualsWithDelta(4250.4, $out['detalles'][0]['_total_neto_exacto'], 0.001);
        $this->assertSame(4250, $out['detalles'][0]['total_neto']);
    }

    public function test_normaliza_booleano_is_personalizable(): void
    {
        $out = $this->prepare([
            'detalles' => [
                ['producto' => 'A', 'cantidad' => 1, 'is_personalizable' => 'true'],
                ['producto' => 'B', 'cantidad' => 1, 'is_personalizable' => '0'],
            ],
        ]);

        $this->assertTrue($out['detalles'][0]['is_personalizable']);
        $this->assertFalse($out['detalles'][1]['is_personalizable']);
    }

    public function test_validator_exige_sku_o_producto_en_cada_detalle(): void
    {
        $request = CotizacionUpdateRequest::create('/x', 'PATCH', [
            'detalles' => [
                ['cantidad' => 1, 'precio_unitario' => 100],
            ],
        ]);

        $validator = validator(
            $request->all(),
            $request->rules()
        );
        $request->withValidator($validator);

        $this->assertTrue($validator->fails());
        $errors = $validator->errors()->toArray();
        $this->assertArrayHasKey('detalles.0.sku', $errors);
        $this->assertArrayHasKey('detalles.0.producto', $errors);
    }

    public function test_validator_acepta_detalle_con_solo_producto(): void
    {
        $request = CotizacionUpdateRequest::create('/x', 'PATCH', [
            'detalles' => [
                ['producto' => 'Casco', 'cantidad' => 1, 'precio_unitario' => 1000],
            ],
        ]);

        $validator = validator($request->all(), $request->rules());
        $request->withValidator($validator);

        // Pasa la validación custom (no agrega errores de sku/producto)
        $errors = $validator->errors()->toArray();
        $this->assertArrayNotHasKey('detalles.0.sku', $errors);
        $this->assertArrayNotHasKey('detalles.0.producto', $errors);
    }
}
