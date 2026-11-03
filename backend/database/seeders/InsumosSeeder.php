<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InsumosSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('pte_insumos')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $insumos = [
            'BOTON', 'BROCHE', 'CIERRE 1/2 POLERON', 'CIERRE BOLSILLOS', 
            'CIERRE CENTRAL DP 75 AZUL', 'CIERRE CENTRAL DP 75 NEGRO', 
            'CIERRE CENTRAL DP 80 AZUL', 'CIERRE CENTRAL DP 80 NEGRO', 
            'CIERRE CENTRAL DP 85 AZUL', 'CIERRE CENTRAL DP 85 NEGRO', 
            'CIERRE CENTRAL FR NEGRO', 'CIERRE CENTRAL N5 INV 75 AZUL', 
            'CIERRE CENTRAL N5 INV 80 AZUL', 'CIERRE CENTRAL N5 INV 85 AZUL', 
            'CIERRE PANTALON', 'CINTA ESPIGA', 'CORDON ELASTICO', 'ELASTICO PUÑO', 
            'FORRO', 'REFLECTIVO FR', 'REFLECTIVO TELA', 'FUSIONADO CUELLO', 
            'FUSIONADO PATA', 'OJETILLOS', 'PASADOR', 'PRETINA TEJIDO', 
            'PUÑO TEJIDO', 'PUÑO TROQUELADO', 'REFLECTANTE DIA Y NOCHE', 
            'REFLECTANTE GRIS', 'RELLENO CUELLO', 'TALLAS Y ETIQUETAS', 'TANKAS', 
            'TELA APLICACIÓN', 'TELA BOLSILLOS', 'TELA PRINCIPAL', 'VELCRO 10', 
            'VELCRO 2,5', 'VELCRO 5', 'VELCRO BOLSILLO CH 14 CMS X 2 (S-D)', 
            'VELCRO CUELLO 9 CMS X 2 (S-D)', 'VELCRO LINTERNA 5 CMS X 1 (S-D)', 
            'VELCRO RADIO 7 CMS X 1 (S-D)', 'VELCRO TAPETA CENTRAL 7 CMS X 3 (S-D)', 
            'VELCRO TRABA PUÑO 14/7 CMS X 2', 'VIVO COLLERETE', 
            'VELCRO TRABA BASTA 14/7 CM X2', 'VELCRO AJUSTE PRETINA 14/7 CM X2', 
            'VELCRO BOLSILLO LAT. PANT. 14 CM X2', 'VELCRO BOLSILLO TRAS. PANT. 10 CM X2', 
            'TELA BOLSILLO FR PANTALON', 'CIERRE PANTALON FR 16 CM NEGRO', 'BORDADOS', 
            'CIERRE BRONCE AZUL 16 CM', 'CIERRE BRONCE NEGRO 16 CM', 
            'CIERRE BRONCE BEIGE 16 CM', 'ELASTICO PANTALON', 
            'CIERRE BASTA NYLON 5 NEGRO 20 CM', 'ESCALERAS', 'TRIANGULOS', 
            'CIERRE GEO. FRONTAL NYLON 8 20 CM', 'CIERRE GEO. BOLSILLO ESPALDA NYLON 8 45 CM', 
            'CIERRE GEO. CENTRAL DIENTE PERRO 45 CM', 'CINTA GEOLOGO', 
            'VELCRO 2,5 CH GEO. 8 CM x7', 'VELCRO 2,5 CH GEO. 15 CM x2', 
            'VELCRO 10 CH GEO. 25 CM', 'CIERRE BOLSILLO N5 16CM AZUL', 
            'CIERRE BOLSILLO N5 16CM NEGRO', 'CINTA 3,8', 'CINTA 2,5', 'CINTA 5,0', 
            'CIERRE N°8', 'CARROS N°8'
        ];

        // 3. INSERCIÓN
        $data = [];
        foreach ($insumos as $nombre) {
            $data[] = [
                'nombre' => $nombre,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('pte_insumos')->insert($data);
    }
}
