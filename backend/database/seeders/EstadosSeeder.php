<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Estado;

class EstadosSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creando estados para Oportunidad, Cotización y Petición...');

        // 1. Estados Oportunidad
        $estadosOportunidad = [
            ['nombre' => 'Nuevo', 'color' => '#111827', 'orden' => 10, 'next_estado_id' => null],
            ['nombre' => 'Contactado', 'color' => '#2563eb', 'orden' => 20, 'next_estado_id' => null],
            ['nombre' => 'En calificación', 'color' => '#7c3aed', 'orden' => 30, 'next_estado_id' => null],
            ['nombre' => 'Enviar cotización', 'color' => '#f59e0b', 'orden' => 40, 'next_estado_id' => null],
            ['nombre' => 'Cotización enviada', 'color' => '#0ea5e9', 'orden' => 50, 'next_estado_id' => null],
            ['nombre' => 'En negociación', 'color' => '#f97316', 'orden' => 60, 'next_estado_id' => null],
            ['nombre' => 'Cerrada ganada', 'color' => '#16a34a', 'orden' => 70, 'next_estado_id' => null],
            ['nombre' => 'Cerrada perdida', 'color' => '#dc2626', 'orden' => 80, 'next_estado_id' => null],
        ];

        // 2. Estados Cotización
        $estadosCotizacion = [
            ['nombre' => 'Cotización Enviada', 'color' => 'yellow', 'orden' => 1, 'next_estado_id' => null],
            ['nombre' => 'Ventas', 'color' => 'green', 'orden' => 2, 'next_estado_id' => null],
            ['nombre' => 'Rechazado', 'color' => 'red', 'orden' => 3, 'next_estado_id' => null],
            ['nombre' => 'Anulado', 'color' => '#64748b', 'orden' => 4, 'next_estado_id' => null],
            ['nombre' => 'No Vigente', 'color' => '#475569', 'orden' => 5, 'next_estado_id' => null],
        ];

        // 3. Estados Petición
        $estadosPeticion = [
            ['nombre' => 'Pendiente', 'color' => 'blue', 'orden' => 1, 'next_estado_id' => null],
            ['nombre' => 'Despachado', 'color' => 'green', 'orden' => 2, 'next_estado_id' => null],
            ['nombre' => 'Anulado', 'color' => 'red', 'orden' => 3, 'next_estado_id' => null],
        ];

        foreach ($estadosOportunidad as $e) {
            Estado::updateOrCreate(
                ['scope' => 'oportunidad', 'nombre' => $e['nombre']],
                ['color' => $e['color'], 'orden' => $e['orden'], 'next_estado_id' => $e['next_estado_id']]
            );
        }

        foreach ($estadosCotizacion as $e) {
            Estado::updateOrCreate(
                ['scope' => 'cotizacion', 'nombre' => $e['nombre']],
                ['color' => $e['color'], 'orden' => $e['orden'], 'next_estado_id' => $e['next_estado_id']]
            );
        }

        foreach ($estadosPeticion as $e) {
            Estado::updateOrCreate(
                ['scope' => 'peticion', 'nombre' => $e['nombre']],
                ['color' => $e['color'], 'orden' => $e['orden'], 'next_estado_id' => $e['next_estado_id']]
            );
        }
    }
}
