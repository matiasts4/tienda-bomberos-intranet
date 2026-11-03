<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CatalogosSeeder::class,
            ProspeccionTipoSeeder::class,
            CotizacionTemporalSeeder::class,
            MpColoresSeeder::class,
            MpCatalogosSeeder::class,
            InsumosSeeder::class,
            EstadosSeeder::class,
            AdminUserSeeder::class,
            RedBomberosSeeder::class,
            ImportBomberosSeeder::class,
            ComercialSkusGenericosSeeder::class,
        ]);
    }
}
