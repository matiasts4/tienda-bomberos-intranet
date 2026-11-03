<?php

namespace Tests\Unit\Comercial;

use App\Support\DeliveryDeadline;
use PHPUnit\Framework\TestCase;

class DeliveryDeadlineTest extends TestCase
{
    public function test_dias_corridos(): void
    {
        $this->assertSame('2026-10-26', DeliveryDeadline::fechaEntrega('2026-10-06', '20 dias'));
    }

    public function test_dias_habiles_saltan_fines_de_semana(): void
    {
        // Martes 06-10 + 15 días hábiles = martes 27-10
        $this->assertSame('2026-10-27', DeliveryDeadline::fechaEntrega('2026-10-06', '15 dias habiles'));
    }

    public function test_sin_plazo_usa_default(): void
    {
        $this->assertSame('2026-10-26', DeliveryDeadline::fechaEntrega('2026-10-06', null));
    }

    public function test_stock_disponible_es_el_mismo_dia(): void
    {
        $this->assertSame('2026-10-06', DeliveryDeadline::fechaEntrega('2026-10-06', 'Stock Disponible'));
    }

    public function test_sin_fecha_venta_o_plazo_ilegible_devuelve_null(): void
    {
        $this->assertNull(DeliveryDeadline::fechaEntrega(null, '20 dias'));
        $this->assertNull(DeliveryDeadline::fechaEntrega('2026-10-06', 'a convenir'));
    }
}
