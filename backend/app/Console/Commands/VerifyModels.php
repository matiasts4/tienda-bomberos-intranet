<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\DetalleCotizacion;
use App\Models\Oportunidad;
use App\Models\Ejecutivo;
use App\Models\Estado;
use App\Models\Despacho;
use App\Models\Pago;
use App\Models\Peticion;

class VerifyModels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'models:verify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica que todos los modelos estén correctamente configurados';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Verificando modelos...');
        $this->newLine();

        $models = [
            'Cliente' => ['table' => 'clientes', 'class' => Cliente::class],
            'Cotizacion' => ['table' => 'cotizaciones', 'class' => Cotizacion::class],
            'DetalleCotizacion' => ['table' => 'detalle_cotizaciones', 'class' => DetalleCotizacion::class],
            'Oportunidad' => ['table' => 'oportunidades', 'class' => Oportunidad::class],
            'Ejecutivo' => ['table' => 'ejecutivos', 'class' => Ejecutivo::class],
            'Estado' => ['table' => 'estados', 'class' => Estado::class],
            'Despacho' => ['table' => 'despachos', 'class' => Despacho::class],
            'Pago' => ['table' => 'pagos', 'class' => Pago::class],
            'Peticion' => ['table' => 'peticiones', 'class' => Peticion::class],
        ];

        $allOk = true;

        foreach ($models as $name => $config) {
            $this->line("Verificando <comment>{$name}</comment>...");
            
            // Verificar que la tabla existe
            if (!Schema::hasTable($config['table'])) {
                $this->error("  ❌ La tabla '{$config['table']}' no existe");
                $allOk = false;
                continue;
            }

            // Verificar que el modelo puede conectarse a la tabla
            try {
                $count = $config['class']::count();
                $this->info("  ✅ Tabla '{$config['table']}' existe y es accesible (registros: {$count})");
            } catch (\Exception $e) {
                $this->error("  ❌ Error al acceder a la tabla: " . $e->getMessage());
                $allOk = false;
            }

            // Verificar que el nombre de tabla del modelo es correcto
            $model = new $config['class'];
            $modelTable = $model->getTable();
            if ($modelTable !== $config['table']) {
                $this->warn("  ⚠️  El modelo usa la tabla '{$modelTable}' pero se esperaba '{$config['table']}'");
            }
        }

        $this->newLine();
        
        if ($allOk) {
            $this->info('✅ Todos los modelos están correctamente configurados');
        } else {
            $this->error('❌ Se encontraron problemas en algunos modelos');
            return 1;
        }

        return 0;
    }
}
