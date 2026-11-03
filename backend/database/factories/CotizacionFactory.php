<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Estado;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cotizacion>
 */
class CotizacionFactory extends Factory
{
    protected $model = Cotizacion::class;

    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'user_id' => User::factory(),
            'estado_id' => Estado::factory()->cotizacion('Cotización Enviada'),
            'numero' => 'CT-' . fake()->unique()->numerify('####'),
            'fecha_creacion' => now()->toDateString(),
            'fecha_vencimiento' => now()->addWeekdays(15)->toDateString(),
            'origen' => 'clientes',
            'plazo_produccion' => '20 dias',
            'condiciones_pago' => 'Contado - Transferencia',
            'despacho' => 'Por pagar',
            'total_neto' => 0,
            'iva' => 0,
            'total' => 0,
        ];
    }

    public function withEstado(Estado $estado): static
    {
        return $this->state(fn () => ['estado_id' => $estado->id]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    public function forCliente(Cliente $cliente): static
    {
        return $this->state(fn () => ['cliente_id' => $cliente->id]);
    }
}
