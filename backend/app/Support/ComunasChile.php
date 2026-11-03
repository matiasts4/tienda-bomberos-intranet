<?php

namespace App\Support;

/**
 * Catálogo oficial de las 346 comunas de Chile.
 *
 * Las claves del arreglo son los `regiones.id` que usa este sistema. OJO: NO son
 * los códigos CUT oficiales; corresponden al mapa histórico que fija
 * App\Console\Commands\FixRegiones (1 = Tarapacá ... 13 = Metropolitana,
 * 15 = Arica y Parinacota, 16 = Ñuble) y del que ya cuelgan los IDs de cuerpos y
 * compañías, así que hay que respetarlo.
 *
 * Cada comuna trae además el nombre de su provincia, para poder enlazar
 * `provincia_id` cuando la provincia ya existe en la BD.
 */
class ComunasChile
{
    /**
     * @return array<int, array<int, array{0:string,1:string}>> region_id => [[comuna, provincia], ...]
     */
    public static function porRegion(): array
    {
        return [
            // 15 - Arica y Parinacota
            15 => [
                ['Arica', 'Arica'],
                ['Camarones', 'Arica'],
                ['Putre', 'Parinacota'],
                ['General Lagos', 'Parinacota'],
            ],

            // 1 - Tarapacá
            1 => [
                ['Iquique', 'Iquique'],
                ['Alto Hospicio', 'Iquique'],
                ['Pozo Almonte', 'Tamarugal'],
                ['Camiña', 'Tamarugal'],
                ['Colchane', 'Tamarugal'],
                ['Huara', 'Tamarugal'],
                ['Pica', 'Tamarugal'],
            ],

            // 2 - Antofagasta
            2 => [
                ['Antofagasta', 'Antofagasta'],
                ['Mejillones', 'Antofagasta'],
                ['Sierra Gorda', 'Antofagasta'],
                ['Taltal', 'Antofagasta'],
                ['Calama', 'El Loa'],
                ['Ollagüe', 'El Loa'],
                ['San Pedro de Atacama', 'El Loa'],
                ['Tocopilla', 'Tocopilla'],
                ['María Elena', 'Tocopilla'],
            ],

            // 3 - Atacama
            3 => [
                ['Copiapó', 'Copiapó'],
                ['Caldera', 'Copiapó'],
                ['Tierra Amarilla', 'Copiapó'],
                ['Chañaral', 'Chañaral'],
                ['Diego de Almagro', 'Chañaral'],
                ['Vallenar', 'Huasco'],
                ['Alto del Carmen', 'Huasco'],
                ['Freirina', 'Huasco'],
                ['Huasco', 'Huasco'],
            ],

            // 4 - Coquimbo
            4 => [
                ['La Serena', 'Elqui'],
                ['Coquimbo', 'Elqui'],
                ['Andacollo', 'Elqui'],
                ['La Higuera', 'Elqui'],
                ['Paihuano', 'Elqui'],
                ['Vicuña', 'Elqui'],
                ['Illapel', 'Choapa'],
                ['Canela', 'Choapa'],
                ['Los Vilos', 'Choapa'],
                ['Salamanca', 'Choapa'],
                ['Ovalle', 'Limarí'],
                ['Combarbalá', 'Limarí'],
                ['Monte Patria', 'Limarí'],
                ['Punitaqui', 'Limarí'],
                ['Río Hurtado', 'Limarí'],
            ],

            // 5 - Valparaíso
            5 => [
                ['Valparaíso', 'Valparaíso'],
                ['Casablanca', 'Valparaíso'],
                ['Concón', 'Valparaíso'],
                ['Juan Fernández', 'Valparaíso'],
                ['Puchuncaví', 'Valparaíso'],
                ['Quintero', 'Valparaíso'],
                ['Viña del Mar', 'Valparaíso'],
                ['Isla de Pascua', 'Isla de Pascua'],
                ['Los Andes', 'Los Andes'],
                ['Calle Larga', 'Los Andes'],
                ['Rinconada', 'Los Andes'],
                ['San Esteban', 'Los Andes'],
                ['La Ligua', 'Petorca'],
                ['Cabildo', 'Petorca'],
                ['Papudo', 'Petorca'],
                ['Petorca', 'Petorca'],
                ['Zapallar', 'Petorca'],
                ['Quillota', 'Quillota'],
                ['La Calera', 'Quillota'],
                ['Hijuelas', 'Quillota'],
                ['La Cruz', 'Quillota'],
                ['Nogales', 'Quillota'],
                ['San Antonio', 'San Antonio'],
                ['Algarrobo', 'San Antonio'],
                ['Cartagena', 'San Antonio'],
                ['El Quisco', 'San Antonio'],
                ['El Tabo', 'San Antonio'],
                ['Santo Domingo', 'San Antonio'],
                ['San Felipe', 'San Felipe de Aconcagua'],
                ['Catemu', 'San Felipe de Aconcagua'],
                ['Llaillay', 'San Felipe de Aconcagua'],
                ['Panquehue', 'San Felipe de Aconcagua'],
                ['Putaendo', 'San Felipe de Aconcagua'],
                ['Santa María', 'San Felipe de Aconcagua'],
                ['Quilpué', 'Marga Marga'],
                ['Limache', 'Marga Marga'],
                ['Olmué', 'Marga Marga'],
                ['Villa Alemana', 'Marga Marga'],
            ],

            // 13 - Metropolitana de Santiago
            13 => [
                ['Santiago', 'Santiago'],
                ['Cerrillos', 'Santiago'],
                ['Cerro Navia', 'Santiago'],
                ['Conchalí', 'Santiago'],
                ['El Bosque', 'Santiago'],
                ['Estación Central', 'Santiago'],
                ['Huechuraba', 'Santiago'],
                ['Independencia', 'Santiago'],
                ['La Cisterna', 'Santiago'],
                ['La Florida', 'Santiago'],
                ['La Granja', 'Santiago'],
                ['La Pintana', 'Santiago'],
                ['La Reina', 'Santiago'],
                ['Las Condes', 'Santiago'],
                ['Lo Barnechea', 'Santiago'],
                ['Lo Espejo', 'Santiago'],
                ['Lo Prado', 'Santiago'],
                ['Macul', 'Santiago'],
                ['Maipú', 'Santiago'],
                ['Ñuñoa', 'Santiago'],
                ['Pedro Aguirre Cerda', 'Santiago'],
                ['Peñalolén', 'Santiago'],
                ['Providencia', 'Santiago'],
                ['Pudahuel', 'Santiago'],
                ['Quilicura', 'Santiago'],
                ['Quinta Normal', 'Santiago'],
                ['Recoleta', 'Santiago'],
                ['Renca', 'Santiago'],
                ['San Joaquín', 'Santiago'],
                ['San Miguel', 'Santiago'],
                ['San Ramón', 'Santiago'],
                ['Vitacura', 'Santiago'],
                ['Puente Alto', 'Cordillera'],
                ['Pirque', 'Cordillera'],
                ['San José de Maipo', 'Cordillera'],
                ['Colina', 'Chacabuco'],
                ['Lampa', 'Chacabuco'],
                ['Tiltil', 'Chacabuco'],
                ['San Bernardo', 'Maipo'],
                ['Buin', 'Maipo'],
                ['Calera de Tango', 'Maipo'],
                ['Paine', 'Maipo'],
                ['Melipilla', 'Melipilla'],
                ['Alhué', 'Melipilla'],
                ['Curacaví', 'Melipilla'],
                ['María Pinto', 'Melipilla'],
                ['San Pedro', 'Melipilla'],
                ['Talagante', 'Talagante'],
                ['El Monte', 'Talagante'],
                ['Isla de Maipo', 'Talagante'],
                ['Padre Hurtado', 'Talagante'],
                ['Peñaflor', 'Talagante'],
            ],

            // 6 - Libertador General Bernardo O'Higgins
            6 => [
                ['Rancagua', 'Cachapoal'],
                ['Codegua', 'Cachapoal'],
                ['Coinco', 'Cachapoal'],
                ['Coltauco', 'Cachapoal'],
                ['Doñihue', 'Cachapoal'],
                ['Graneros', 'Cachapoal'],
                ['Las Cabras', 'Cachapoal'],
                ['Machalí', 'Cachapoal'],
                ['Malloa', 'Cachapoal'],
                ['Mostazal', 'Cachapoal'],
                ['Olivar', 'Cachapoal'],
                ['Peumo', 'Cachapoal'],
                ['Pichidegua', 'Cachapoal'],
                ['Quinta de Tilcoco', 'Cachapoal'],
                ['Rengo', 'Cachapoal'],
                ['Requínoa', 'Cachapoal'],
                ['San Vicente', 'Cachapoal'],
                ['Pichilemu', 'Cardenal Caro'],
                ['La Estrella', 'Cardenal Caro'],
                ['Litueche', 'Cardenal Caro'],
                ['Marchihue', 'Cardenal Caro'],
                ['Navidad', 'Cardenal Caro'],
                ['Paredones', 'Cardenal Caro'],
                ['San Fernando', 'Colchagua'],
                ['Chépica', 'Colchagua'],
                ['Chimbarongo', 'Colchagua'],
                ['Lolol', 'Colchagua'],
                ['Nancagua', 'Colchagua'],
                ['Palmilla', 'Colchagua'],
                ['Peralillo', 'Colchagua'],
                ['Placilla', 'Colchagua'],
                ['Pumanque', 'Colchagua'],
                ['Santa Cruz', 'Colchagua'],
            ],

            // 7 - Maule
            7 => [
                ['Talca', 'Talca'],
                ['Constitución', 'Talca'],
                ['Curepto', 'Talca'],
                ['Empedrado', 'Talca'],
                ['Maule', 'Talca'],
                ['Pelarco', 'Talca'],
                ['Pencahue', 'Talca'],
                ['Río Claro', 'Talca'],
                ['San Clemente', 'Talca'],
                ['San Rafael', 'Talca'],
                ['Cauquenes', 'Cauquenes'],
                ['Chanco', 'Cauquenes'],
                ['Pelluhue', 'Cauquenes'],
                ['Curicó', 'Curicó'],
                ['Hualañé', 'Curicó'],
                ['Licantén', 'Curicó'],
                ['Molina', 'Curicó'],
                ['Rauco', 'Curicó'],
                ['Romeral', 'Curicó'],
                ['Sagrada Familia', 'Curicó'],
                ['Teno', 'Curicó'],
                ['Vichuquén', 'Curicó'],
                ['Linares', 'Linares'],
                ['Colbún', 'Linares'],
                ['Longaví', 'Linares'],
                ['Parral', 'Linares'],
                ['Retiro', 'Linares'],
                ['San Javier', 'Linares'],
                ['Villa Alegre', 'Linares'],
                ['Yerbas Buenas', 'Linares'],
            ],

            // 16 - Ñuble
            16 => [
                ['Chillán', 'Diguillín'],
                ['Bulnes', 'Diguillín'],
                ['Chillán Viejo', 'Diguillín'],
                ['El Carmen', 'Diguillín'],
                ['Pemuco', 'Diguillín'],
                ['Pinto', 'Diguillín'],
                ['Quillón', 'Diguillín'],
                ['San Ignacio', 'Diguillín'],
                ['Yungay', 'Diguillín'],
                ['Quirihue', 'Itata'],
                ['Cobquecura', 'Itata'],
                ['Coelemu', 'Itata'],
                ['Ninhue', 'Itata'],
                ['Portezuelo', 'Itata'],
                ['Ránquil', 'Itata'],
                ['Treguaco', 'Itata'],
                ['San Carlos', 'Punilla'],
                ['Coihueco', 'Punilla'],
                ['Ñiquén', 'Punilla'],
                ['San Fabián', 'Punilla'],
                ['San Nicolás', 'Punilla'],
            ],

            // 8 - Biobío
            8 => [
                ['Concepción', 'Concepción'],
                ['Coronel', 'Concepción'],
                ['Chiguayante', 'Concepción'],
                ['Florida', 'Concepción'],
                ['Hualqui', 'Concepción'],
                ['Lota', 'Concepción'],
                ['Penco', 'Concepción'],
                ['San Pedro de la Paz', 'Concepción'],
                ['Santa Juana', 'Concepción'],
                ['Talcahuano', 'Concepción'],
                ['Tomé', 'Concepción'],
                ['Hualpén', 'Concepción'],
                ['Lebu', 'Arauco'],
                ['Arauco', 'Arauco'],
                ['Cañete', 'Arauco'],
                ['Contulmo', 'Arauco'],
                ['Curanilahue', 'Arauco'],
                ['Los Álamos', 'Arauco'],
                ['Tirúa', 'Arauco'],
                ['Los Ángeles', 'Biobío'],
                ['Antuco', 'Biobío'],
                ['Cabrero', 'Biobío'],
                ['Laja', 'Biobío'],
                ['Mulchén', 'Biobío'],
                ['Nacimiento', 'Biobío'],
                ['Negrete', 'Biobío'],
                ['Quilaco', 'Biobío'],
                ['Quilleco', 'Biobío'],
                ['San Rosendo', 'Biobío'],
                ['Santa Bárbara', 'Biobío'],
                ['Tucapel', 'Biobío'],
                ['Yumbel', 'Biobío'],
                ['Alto Biobío', 'Biobío'],
            ],

            // 9 - La Araucanía
            9 => [
                ['Temuco', 'Cautín'],
                ['Carahue', 'Cautín'],
                ['Cunco', 'Cautín'],
                ['Curarrehue', 'Cautín'],
                ['Freire', 'Cautín'],
                ['Galvarino', 'Cautín'],
                ['Gorbea', 'Cautín'],
                ['Lautaro', 'Cautín'],
                ['Loncoche', 'Cautín'],
                ['Melipeuco', 'Cautín'],
                ['Nueva Imperial', 'Cautín'],
                ['Padre Las Casas', 'Cautín'],
                ['Perquenco', 'Cautín'],
                ['Pitrufquén', 'Cautín'],
                ['Pucón', 'Cautín'],
                ['Saavedra', 'Cautín'],
                ['Teodoro Schmidt', 'Cautín'],
                ['Toltén', 'Cautín'],
                ['Vilcún', 'Cautín'],
                ['Villarrica', 'Cautín'],
                ['Cholchol', 'Cautín'],
                ['Angol', 'Malleco'],
                ['Collipulli', 'Malleco'],
                ['Curacautín', 'Malleco'],
                ['Ercilla', 'Malleco'],
                ['Lonquimay', 'Malleco'],
                ['Los Sauces', 'Malleco'],
                ['Lumaco', 'Malleco'],
                ['Purén', 'Malleco'],
                ['Renaico', 'Malleco'],
                ['Traiguén', 'Malleco'],
                ['Victoria', 'Malleco'],
            ],

            // 14 - Los Ríos
            14 => [
                ['Valdivia', 'Valdivia'],
                ['Corral', 'Valdivia'],
                ['Lanco', 'Valdivia'],
                ['Los Lagos', 'Valdivia'],
                ['Máfil', 'Valdivia'],
                ['Mariquina', 'Valdivia'],
                ['Paillaco', 'Valdivia'],
                ['Panguipulli', 'Valdivia'],
                ['La Unión', 'Ranco'],
                ['Futrono', 'Ranco'],
                ['Lago Ranco', 'Ranco'],
                ['Río Bueno', 'Ranco'],
            ],

            // 10 - Los Lagos
            10 => [
                ['Puerto Montt', 'Llanquihue'],
                ['Calbuco', 'Llanquihue'],
                ['Cochamó', 'Llanquihue'],
                ['Fresia', 'Llanquihue'],
                ['Frutillar', 'Llanquihue'],
                ['Los Muermos', 'Llanquihue'],
                ['Llanquihue', 'Llanquihue'],
                ['Maullín', 'Llanquihue'],
                ['Puerto Varas', 'Llanquihue'],
                ['Castro', 'Chiloé'],
                ['Ancud', 'Chiloé'],
                ['Chonchi', 'Chiloé'],
                ['Curaco de Vélez', 'Chiloé'],
                ['Dalcahue', 'Chiloé'],
                ['Puqueldón', 'Chiloé'],
                ['Queilén', 'Chiloé'],
                ['Quellón', 'Chiloé'],
                ['Quemchi', 'Chiloé'],
                ['Quinchao', 'Chiloé'],
                ['Osorno', 'Osorno'],
                ['Puerto Octay', 'Osorno'],
                ['Purranque', 'Osorno'],
                ['Puyehue', 'Osorno'],
                ['Río Negro', 'Osorno'],
                ['San Juan de la Costa', 'Osorno'],
                ['San Pablo', 'Osorno'],
                ['Chaitén', 'Palena'],
                ['Futaleufú', 'Palena'],
                ['Hualaihué', 'Palena'],
                ['Palena', 'Palena'],
            ],

            // 11 - Aysén del General Carlos Ibáñez del Campo
            11 => [
                ['Coyhaique', 'Coyhaique'],
                ['Lago Verde', 'Coyhaique'],
                ['Aysén', 'Aysén'],
                ['Cisnes', 'Aysén'],
                ['Guaitecas', 'Aysén'],
                ['Cochrane', 'Capitán Prat'],
                ["O'Higgins", 'Capitán Prat'],
                ['Tortel', 'Capitán Prat'],
                ['Chile Chico', 'General Carrera'],
                ['Río Ibáñez', 'General Carrera'],
            ],

            // 12 - Magallanes y de la Antártica Chilena
            12 => [
                ['Punta Arenas', 'Magallanes'],
                ['Laguna Blanca', 'Magallanes'],
                ['Río Verde', 'Magallanes'],
                ['San Gregorio', 'Magallanes'],
                ['Cabo de Hornos', 'Antártica Chilena'],
                ['Antártica', 'Antártica Chilena'],
                ['Porvenir', 'Tierra del Fuego'],
                ['Primavera', 'Tierra del Fuego'],
                ['Timaukel', 'Tierra del Fuego'],
                ['Natales', 'Última Esperanza'],
                ['Torres del Paine', 'Última Esperanza'],
            ],
        ];
    }

    /**
     * Lista plana: [['nombre' => ..., 'region_id' => ..., 'provincia' => ...], ...]
     *
     * @return array<int, array{nombre:string, region_id:int, provincia:string}>
     */
    public static function planas(): array
    {
        $out = [];
        foreach (self::porRegion() as $regionId => $comunas) {
            foreach ($comunas as [$comuna, $provincia]) {
                $out[] = [
                    'nombre' => $comuna,
                    'region_id' => (int) $regionId,
                    'provincia' => $provincia,
                ];
            }
        }

        return $out;
    }
}
