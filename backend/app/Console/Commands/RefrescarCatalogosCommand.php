<?php

namespace App\Console\Commands;

use App\Http\Controllers\CatalogoController;
use Illuminate\Console\Command;

class RefrescarCatalogosCommand extends Command
{
    protected $signature = 'catalogos:refrescar';

    protected $description = 'Limpia la caché de catálogos PTE (usar tras insertar o editar registros por SQL)';

    public function handle(): int
    {
        CatalogoController::forgetCache();
        $this->info('Caché de catálogos limpiada: '.count(CatalogoController::CACHE_KEYS).' claves.');

        return self::SUCCESS;
    }
}
