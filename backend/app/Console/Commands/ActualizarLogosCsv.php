<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cliente;

class ActualizarLogosCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:actualizar-logos-csv';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza bomberos_chile 2.csv con el logo_url de la tabla clientes.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $csvInPath = base_path('../bomberos_chile 2.csv');
        $csvOutPath = base_path('../bomberos_chile 2_updated.csv');

        if (!file_exists($csvInPath)) {
            $this->error("No se encontró el archivo: $csvInPath");
            return 1;
        }

        $handleIn = fopen($csvInPath, "r");
        $handleOut = fopen($csvOutPath, "w");

        if ($handleIn !== false && $handleOut !== false) {
            $header = fgetcsv($handleIn, 1000, ",");
            fputcsv($handleOut, $header);

            $this->info("Procesando CSV e inyectando URLs de logos...");

            while (($data = fgetcsv($handleIn, 1000, ",")) !== false) {
                $nombreCuerpo = trim($data[1] ?? '');
                if (!$nombreCuerpo) {
                    fputcsv($handleOut, $data);
                    continue;
                }

                $dbName = 'CB ' . $nombreCuerpo;
                $cliente = Cliente::where('nombre_empresa', $dbName)->first();

                // data[3] corresponde a 'Logo Path' y puede contener la nueva URL absoluta
                if ($cliente && $cliente->logo_url) {
                    $data[3] = $cliente->logo_url;
                }

                fputcsv($handleOut, $data);
            }

            fclose($handleIn);
            fclose($handleOut);

            // Reemplazar el archivo original
            rename($csvOutPath, $csvInPath);

            $this->info("Archivo bomberos_chile 2.csv actualizado satisfactoriamente con los logos escrapeados.");
        } else {
            $this->error("No se pudo abrir los archivos CSV para lectura/escritura.");
            return 1;
        }

        return 0;
    }
}
