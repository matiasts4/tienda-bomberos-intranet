<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        // 🛠️ FIX IMPORTANTE: 
        // Esto permite insertar el ID 0 sin que MySQL lo convierta en 1 automáticamente.
        DB::statement("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO';");

        // 1. LIMPIEZA TOTAL
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        $tablas = [
            'pte_skus', 'pte_stocks', 
            'pte_unidades_negocio', 'pte_origenes', 'pte_grp_familias',
            'pte_familias', 'pte_subfamilias', 'pte_familia_tipos',
            'pte_familia_formatos', 'pte_generos', 'pte_colores', 'pte_tallas'
        ];

        foreach ($tablas as $tabla) {
            DB::table($tabla)->truncate();
        }
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // --------------------------------------------------------
        // 2. POBLADO DE DATOS (CON TIMESTAMPS)
        // --------------------------------------------------------

        // A. UNIDADES DE NEGOCIO
        DB::table('pte_unidades_negocio')->insert([
            ['id' => 1, 'nombre' => 'Tienda Bombero', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'nombre' => 'Portal Corporativo', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'nombre' => 'Optica', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // B. GRUPOS DE FAMILIA
        DB::table('pte_grp_familias')->insert([
            ['id' => 1, 'nombre' => 'Vestuario', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7, 'nombre' => 'Accesorios', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // C. ORIGENES
        DB::table('pte_origenes')->insert([
            ['id' => 1, 'grp_familia_id' => 1, 'nombre' => 'Nacional TB', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'grp_familia_id' => 1, 'nombre' => 'Nacional PC', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'grp_familia_id' => 1, 'nombre' => 'Nacional EXT', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'grp_familia_id' => 1, 'nombre' => 'Importado', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // D. FAMILIAS
        DB::table('pte_familias')->insert([
            ['id' => 0, 'nombre' => 'Generico', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 1, 'nombre' => 'Camisas', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'nombre' => 'Poleras', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'nombre' => 'Polerones', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'nombre' => 'Chaquetas', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'nombre' => 'Pantalones', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'nombre' => 'Kit', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7, 'nombre' => 'Accesorios', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 8, 'nombre' => 'Suvenir', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 9, 'nombre' => 'Otros', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // E. SUBFAMILIAS
        DB::table('pte_subfamilias')->insert([
            ['id' => 0, 'familia_id' => 0, 'nombre' => 'Generico', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 11, 'familia_id' => 1, 'nombre' => 'Blusa', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12, 'familia_id' => 1, 'nombre' => 'Camisa', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'familia_id' => 2, 'nombre' => 'Polera', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 30, 'familia_id' => 3, 'nombre' => 'Polar', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 31, 'familia_id' => 3, 'nombre' => 'Poleron', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 40, 'familia_id' => 4, 'nombre' => 'Chaqueta', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 41, 'familia_id' => 4, 'nombre' => 'Cortaviento', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 42, 'familia_id' => 4, 'nombre' => 'Reversible', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 43, 'familia_id' => 4, 'nombre' => 'Parka', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 44, 'familia_id' => 4, 'nombre' => 'Geologo', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 50, 'familia_id' => 5, 'nombre' => 'Pantalon', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 60, 'familia_id' => 6, 'nombre' => 'Tenida', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 61, 'familia_id' => 6, 'nombre' => 'Trajes', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 62, 'familia_id' => 6, 'nombre' => 'Uniformes', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 70, 'familia_id' => 7, 'nombre' => 'Gorro', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 71, 'familia_id' => 7, 'nombre' => 'Mochila', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // F. FORMATOS (Aquí aplicamos los IDs 21, 22, 23, 24 acordados)
        DB::table('pte_familia_formatos')->insert([
            // --- GENERICO ---
            ['id' => 0, 'familia_id' => 0, 'nombre' => 'Generico', 'created_at' => now(), 'updated_at' => now()],

            // --- ACCESORIOS (1 al 20) ---
            ['id' => 1, 'familia_id' => 7, 'nombre' => 'Spandex', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'familia_id' => 7, 'nombre' => 'Capitan', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'familia_id' => 7, 'nombre' => 'Teniente', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'familia_id' => 7, 'nombre' => 'Comandante', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'familia_id' => 7, 'nombre' => 'Girls', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'familia_id' => 7, 'nombre' => 'Forestal', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7, 'familia_id' => 7, 'nombre' => 'Rescate Vehicular', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 8, 'familia_id' => 7, 'nombre' => 'Rescate Urbano', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 9, 'familia_id' => 7, 'nombre' => 'Brigada Juvenil', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 10, 'familia_id' => 7, 'nombre' => 'Gersa', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 11, 'familia_id' => 7, 'nombre' => 'Autoridad Director', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12, 'familia_id' => 7, 'nombre' => 'Autoridad Secretario', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 13, 'familia_id' => 7, 'nombre' => 'Autoridad Tesorero', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 14, 'familia_id' => 7, 'nombre' => 'Autoridad Superintendente', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 15, 'familia_id' => 7, 'nombre' => 'Autoridad Vicesuperintendente', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 16, 'familia_id' => 7, 'nombre' => 'Bomb Scuad', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 17, 'familia_id' => 7, 'nombre' => 'Notebook', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 18, 'familia_id' => 7, 'nombre' => 'Hidratacion', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 19, 'familia_id' => 7, 'nombre' => 'Institucional', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'familia_id' => 7, 'nombre' => 'Porta Uniforme', 'created_at' => now(), 'updated_at' => now()],

            // --- ROPA (21 en adelante) ---
            ['id' => 21, 'familia_id' => 1, 'nombre' => 'Manga Corta', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 22, 'familia_id' => 1, 'nombre' => 'Manga Larga', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 23, 'familia_id' => 5, 'nombre' => 'Corto', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 24, 'familia_id' => 5, 'nombre' => 'Largo', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // G. GENEROS
        // Los IDs mapean DIRECTAMENTE al dígito de género en el SKU (posición 10):
        //   0 → Unisex  (SKU: ...X0CC_TT)
        //   1 → Femenino (SKU: ...X1CC_TT)
        //   2 → Masculino (SKU: ...X2CC_TT)
        DB::table('pte_generos')->insert([
            ['id' => 0, 'nombre' => 'Unisex', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 1, 'nombre' => 'Hombre', 'created_at' => now(), 'updated_at' => now()], // Dígito 1
            ['id' => 2, 'nombre' => 'Mujer',  'created_at' => now(), 'updated_at' => now()], // Dígito 2
        ]);

        // H. COLORES
        DB::table('pte_colores')->insert([
            ['id' => 0, 'nombre' => 'Generico', 'created_at' => now(), 'updated_at' => now()], // ID 0 sin problema gracias al sql_mode
            ['id' => 10, 'nombre' => 'Blanco', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'nombre' => 'Amarillo Fluor / Negro', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 21, 'nombre' => 'Amarillo Fluor / Azul Marino', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 30, 'nombre' => 'Azul', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 31, 'nombre' => 'Azul Marino', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 32, 'nombre' => 'Azulino', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 33, 'nombre' => 'Azulino / Negro', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 34, 'nombre' => 'Azul Petroleo', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 35, 'nombre' => 'Celeste', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 36, 'nombre' => 'Azul Marino EST. Naranjo', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 37, 'nombre' => 'Azul Marino EST. Rojo', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 40, 'nombre' => 'Beige', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 50, 'nombre' => 'Verde', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 60, 'nombre' => 'Rojo', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 61, 'nombre' => 'Rojo fluor', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 62, 'nombre' => 'Rojo fluor / Negro', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 64, 'nombre' => 'Ocre', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 65, 'nombre' => 'Naranjo', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 66, 'nombre' => 'Naranjo Fluor', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 67, 'nombre' => 'Naranjo Fluor / Negro', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 70, 'nombre' => 'Gris', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 80, 'nombre' => 'Grafito', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 90, 'nombre' => 'Negro', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 91, 'nombre' => 'Negro / Azulino', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 92, 'nombre' => 'Negro / Blanco', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 93, 'nombre' => 'Negro / Naranjo fluor', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 94, 'nombre' => 'Negro EST. Naranjo', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 95, 'nombre' => 'Negro EST. Verde', 'created_at' => now(), 'updated_at' => now()],

            // --- COLORES ESENCIALES ADICIONALES ---
            ['id' => 22, 'nombre' => 'Amarillo', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 41, 'nombre' => 'Cafe', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 42, 'nombre' => 'Marron', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 51, 'nombre' => 'Verde Militar', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 52, 'nombre' => 'Verde Oliva', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 63, 'nombre' => 'Burdeo', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 68, 'nombre' => 'Rosado', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 69, 'nombre' => 'Salmon', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 71, 'nombre' => 'Gris Claro', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 72, 'nombre' => 'Gris Oscuro', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 96, 'nombre' => 'Camuflaje', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 97, 'nombre' => 'Crema', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // I. TIPOS
        DB::table('pte_familia_tipos')->insert([
            ['id' => 0, 'nombre' => 'Generico', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'nombre' => 'Sin Manga', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 10, 'nombre' => 'Deportivo', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 21, 'nombre' => 'Impermeable', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 22, 'nombre' => 'Termico', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 23, 'nombre' => 'Termico Impermeable', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 30, 'nombre' => 'Polo', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 31, 'nombre' => 'Casual', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 32, 'nombre' => 'Pique', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 33, 'nombre' => 'Oxford', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 34, 'nombre' => 'Urbano(a)', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 35, 'nombre' => 'Drill', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 36, 'nombre' => 'Gabardina', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 40, 'nombre' => 'Quepi', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 41, 'nombre' => 'Lana', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 42, 'nombre' => 'Safari', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 45, 'nombre' => 'Banano', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 46, 'nombre' => 'Mochila', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 47, 'nombre' => 'Bolso', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 50, 'nombre' => 'Pluma', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 60, 'nombre' => 'Cuartel', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 61, 'nombre' => 'Cuartel Casual', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 70, 'nombre' => 'Antiacido', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 71, 'nombre' => 'Multirol', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 72, 'nombre' => 'Rescate', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 80, 'nombre' => 'Agreste', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 81, 'nombre' => 'GERSA', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 82, 'nombre' => 'HAZMAT', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 83, 'nombre' => 'USAR', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 84, 'nombre' => 'Tactico', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 85, 'nombre' => 'Softshell', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 86, 'nombre' => 'Capitan', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 87, 'nombre' => 'Comandante', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 88, 'nombre' => 'Teniente', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 89, 'nombre' => 'Brigadista', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 11, 'nombre' => 'Polo Primera', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12, 'nombre' => 'Polo Segunda', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 13, 'nombre' => 'Polo Tercera', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 14, 'nombre' => 'Polo Cuarta', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 15, 'nombre' => 'Polo Quinta', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 16, 'nombre' => 'Polo Sexta', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 17, 'nombre' => 'Polo Septima', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 18, 'nombre' => 'Polo Octava', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 19, 'nombre' => 'Polo Novena', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // J. TALLAS
        DB::table('pte_tallas')->insert([
            ['id' => 0, 'nombre' => 'GENERICO', 'orden' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 10, 'nombre' => 'Unica', 'orden' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 11, 'nombre' => 'XXS', 'orden' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12, 'nombre' => 'XS', 'orden' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 13, 'nombre' => 'S', 'orden' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 14, 'nombre' => 'M', 'orden' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 15, 'nombre' => 'L', 'orden' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 16, 'nombre' => 'XL', 'orden' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 17, 'nombre' => 'XXL', 'orden' => 8, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 18, 'nombre' => '3XL', 'orden' => 9, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 19, 'nombre' => '4XL', 'orden' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'nombre' => 'ESPECIAL', 'orden' => 11, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 21, 'nombre' => 'Ajustable 1', 'orden' => 12, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 22, 'nombre' => 'Ajustable 2', 'orden' => 13, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}