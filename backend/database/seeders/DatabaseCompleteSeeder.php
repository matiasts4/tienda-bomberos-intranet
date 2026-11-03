<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseCompleteSeeder extends Seeder
{
    /**
     * Seed the application's database in the correct order.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            CargosSeeder::class,
            CatalogosSeeder::class,
            ProspeccionTipoSeeder::class,
            CotizacionTemporalSeeder::class,
            MpColoresSeeder::class,
            MpCatalogosSeeder::class,
            InsumosSeeder::class,
            EstadosSeeder::class,
            AdminUserSeeder::class,
            ImportBomberosSeeder::class,
            RedBomberosSeeder::class,
            ComercialSkusGenericosSeeder::class,
        ]);
    }
}
