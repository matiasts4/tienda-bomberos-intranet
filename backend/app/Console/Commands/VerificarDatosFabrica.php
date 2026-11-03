<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\F_MpProveedor;
use App\Models\F_MpMateriaPrima;
use App\Models\F_MpEspecificacion;
use App\Models\F_MpStock;
use App\Models\F_OrdenProduccion;
use App\Models\F_ControlCalidad;
use App\Models\F_Reparacion;
use App\Models\F_FichaTecnica;

class VerificarDatosFabrica extends Command
{
    protected $signature = 'fabrica:verificar';
    protected $description = 'Verifica el estado de los datos en las tablas de fábrica';

    public function handle()
    {
        $this->info('🔍 Verificando datos de Fábrica y MP...');
        $this->newLine();

        $tablas = [
            'F_MpProveedor' => F_MpProveedor::class,
            'F_MpMateriaPrima' => F_MpMateriaPrima::class,
            'F_MpEspecificacion' => F_MpEspecificacion::class,
            'F_MpStock' => F_MpStock::class,
            'F_OrdenProduccion' => F_OrdenProduccion::class,
            'F_ControlCalidad' => F_ControlCalidad::class,
            'F_Reparacion' => F_Reparacion::class,
            'F_FichaTecnica' => F_FichaTecnica::class,
        ];

        foreach ($tablas as $nombre => $clase) {
            $count = $clase::count();
            $emoji = $count > 0 ? '✅' : '❌';
            $this->line("$emoji $nombre: $count registros");
        }

        $this->newLine();
        $this->info('📊 Resumen rápido:');
        
        // Mostrar algunos ejemplos
        $this->line('📦 Proveedores:');
        F_MpProveedor::take(3)->get()->each(function($p) {
            $this->line("   • {$p->nombre_empresa} ({$p->email})");
        });

        $this->line('🧵 Materias Primas:');
        F_MpMateriaPrima::take(3)->get()->each(function($m) {
            $this->line("   • {$m->nombre} ({$m->tipo_material})");
        });

        $this->line('🏭 Órdenes Activas:');
        F_OrdenProduccion::where('estado_produccion', '!=', 'Cancelada')->take(3)->get()->each(function($o) {
            $this->line("   • SKU {$o->sku} - {$o->cantidad_a_producir} unidades - {$o->estado_produccion}");
        });

        $this->newLine();
        $this->info('✅ Verificación completada!');
        
        return Command::SUCCESS;
    }
}
