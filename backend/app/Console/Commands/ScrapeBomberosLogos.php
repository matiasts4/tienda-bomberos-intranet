<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cliente;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class ScrapeBomberosLogos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scrape-bomberos-logos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrapes the bomberos.cl profile URLs from the CSV and updates the logo_url in the clientes table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $csvPath = base_path('../bomberos_chile 2.csv');
        if (!file_exists($csvPath)) {
            $this->error("CSV file not found at: $csvPath");
            return 1;
        }

        $this->info("Iniciando actualización de logo_url desde $csvPath...");

        $handle = fopen($csvPath, "r");
        if ($handle !== false) {
            $header = fgetcsv($handle, 1000, ","); // Skip headers

            $count = 0;
            $success = 0;
            while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                // Expected format ID, Cuerpo de Bomberos, URL, Logo Path, RUT...
                $nombreCuerpo = trim($data[1] ?? '');
                $perfilUrl = trim($data[2] ?? '');

                if (!$nombreCuerpo || !$perfilUrl || filter_var($perfilUrl, FILTER_VALIDATE_URL) === false) {
                    continue;
                }

                $dbName = 'CB ' . $nombreCuerpo;
                $cliente = Cliente::where('nombre_empresa', $dbName)->first();

                if (!$cliente) {
                    $this->warn("Cliente no encontrado en DB: $dbName. Saltando.");
                    continue;
                }

                if ($cliente->logo_url) {
                    $this->comment("Cliente $dbName ya tiene logo: {$cliente->logo_url}. Saltando.");
                    continue;
                }

                $this->info("Procesando: $dbName -> $perfilUrl");

                try {
                    $response = Http::timeout(10)->get($perfilUrl);
                    if ($response->successful()) {
                        $html = $response->body();
                        $crawler = new Crawler($html);

                        // La imagen del logo suele tener la clase img-responsive. Busquemos la que está en la sección del logo.
                        // En la respuesta que vimos: <img src="../admin_crpo_cia/src/logos/cuerpos/cuerpo_Arica_380891276.jpg?v=1.0.0" class="img-responsive" width="150px"/>

                        $logoNode = $crawler->filter('img.img-responsive[src*="cuerpos"]')->first();

                        if ($logoNode->count() > 0) {
                            $src = $logoNode->attr('src');

                            // src puede ser algo como "../admin_crpo_cia/src/logos/cuerpos/archivo.jpg?v=1"
                            // baseUrl es https://app.bomberos.cl/info_crpo_web/

                            // Reemplazamos los ".." inteligentemente
                            $cleanedSrc = str_replace('../', '', $src);
                            // Cortamos el query param ?v=XXX
                            $cleanedSrc = explode('?', $cleanedSrc)[0];

                            // Construimos URL Absoluta
                            $absoluteUrl = "https://app.bomberos.cl/" . $cleanedSrc;

                            $cliente->logo_url = $absoluteUrl;
                            $cliente->save();

                            $this->line("<info>✓ Guardado logo:</info> $absoluteUrl");
                            $success++;
                        } else {
                            $this->error("No se encontró el nodo img para el logo en el HTML de: $dbName");
                        }
                    } else {
                        $this->error("Error GET a $perfilUrl (Status: " . $response->status() . ")");
                    }
                } catch (\Exception $e) {
                    $this->error("Excepción fallida para $dbName : " . $e->getMessage());
                }

                $count++;

                // Un pequeño delay para no hacer flooding al servidor
                usleep(500000); // 0.5 segundos 
            }
            fclose($handle);

            $this->info("Proceso terminado. Procesados: $count. Exitosos: $success.");
        } else {
            $this->error("No se pudo abrir el CSV.");
            return 1;
        }

        return 0;
    }
}
