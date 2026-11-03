<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige clientes cuya región o provincia no corresponde a su comuna (por ejemplo,
 * "Tienda Ñuble": comuna Yungay, que es de la provincia de Diguillín, pero con provincia Itata).
 *
 * La comuna manda: es el dato más específico y el que se elige en pantalla. Solo se tocan
 * region_id y provincia_id; nunca la comuna, el nombre ni updated_at. Es idempotente
 * (una segunda corrida no encuentra nada) y sin --apply solo informa.
 */
class CorregirUbicacionClientesCommand extends Command
{
    protected $signature = 'clientes:corregir-ubicacion
        {--apply : Guarda las correcciones (sin esta opción solo se informa)}
        {--incluir-vacios : Además completa región/provincia vacías a partir de la comuna}
        {--detailed : Muestra también los clientes sin comuna cuya provincia no es de su región}';

    protected $description = 'Corrige la región y provincia de clientes que no coinciden con su comuna';

    public function handle(): int
    {
        $incluirVacios = (bool) $this->option('incluir-vacios');

        $filas = DB::table('clientes as cl')
            ->join('comunas as c', 'c.id', '=', 'cl.comuna_id')
            ->orderBy('cl.id')
            ->get([
                'cl.id', 'cl.nombre_empresa', 'cl.region_id', 'cl.provincia_id',
                'c.nombre as comuna', 'c.region_id as comuna_region_id', 'c.provincia_id as comuna_provincia_id',
            ]);

        $nombres = $this->nombresGeografia();
        $cambios = [];

        foreach ($filas as $f) {
            $motivos = [];
            $nuevaRegion = $f->region_id;
            $nuevaProvincia = $f->provincia_id;

            if ($f->region_id !== null && (int) $f->region_id !== (int) $f->comuna_region_id) {
                $motivos[] = 'región distinta a la de la comuna';
                $nuevaRegion = $f->comuna_region_id;
                // La provincia actual era de otra región: solo se conserva la de la comuna.
                $nuevaProvincia = $f->comuna_provincia_id;
            } elseif ($f->region_id === null && $incluirVacios) {
                $motivos[] = 'región vacía';
                $nuevaRegion = $f->comuna_region_id;
            }

            if ($f->comuna_provincia_id !== null) {
                if ($f->provincia_id !== null && (int) $f->provincia_id !== (int) $f->comuna_provincia_id) {
                    $motivos[] = 'provincia distinta a la de la comuna';
                    $nuevaProvincia = $f->comuna_provincia_id;
                } elseif ($f->provincia_id === null && $incluirVacios) {
                    $motivos[] = 'provincia vacía';
                    $nuevaProvincia = $f->comuna_provincia_id;
                }
            }

            if ($motivos === [] || ((int) $nuevaRegion === (int) $f->region_id && (int) $nuevaProvincia === (int) $f->provincia_id)) {
                continue;
            }

            $cambios[] = [
                'id' => $f->id,
                'nombre' => $f->nombre_empresa,
                'comuna' => $f->comuna,
                'region_antes' => $f->region_id,
                'region_despues' => $nuevaRegion,
                'provincia_antes' => $f->provincia_id,
                'provincia_despues' => $nuevaProvincia,
                'motivo' => implode(' y ', $motivos),
            ];
        }

        $this->info(sprintf('Clientes con comuna revisados: %d. Con ubicación incoherente: %d.', $filas->count(), count($cambios)));

        if ($cambios !== []) {
            $this->table(
                ['ID', 'Cliente', 'Comuna', 'Región', 'Provincia', 'Motivo'],
                array_map(fn ($c) => [
                    $c['id'],
                    mb_strimwidth((string) $c['nombre'], 0, 34, '…'),
                    $c['comuna'],
                    $this->antesDespues($nombres['regiones'], $c['region_antes'], $c['region_despues']),
                    $this->antesDespues($nombres['provincias'], $c['provincia_antes'], $c['provincia_despues']),
                    $c['motivo'],
                ], $cambios)
            );
        }

        if ($this->option('detailed')) {
            $this->mostrarSinComunaIncoherentes($nombres);
        }

        if ($cambios === []) {
            $this->info('Nada que corregir.');

            return self::SUCCESS;
        }

        if (! $this->option('apply')) {
            $this->warn('Simulacro: no se guardó nada. Ejecuta con --apply para aplicar estas correcciones.');

            return self::SUCCESS;
        }

        $guardados = DB::transaction(function () use ($cambios) {
            $n = 0;
            foreach ($cambios as $c) {
                // Se exige que la fila siga como se leyó: si alguien la editó entretanto, no se pisa.
                $n += DB::table('clientes')
                    ->where('id', $c['id'])
                    ->where(fn ($q) => $c['region_antes'] === null ? $q->whereNull('region_id') : $q->where('region_id', $c['region_antes']))
                    ->where(fn ($q) => $c['provincia_antes'] === null ? $q->whereNull('provincia_id') : $q->where('provincia_id', $c['provincia_antes']))
                    ->update(['region_id' => $c['region_despues'], 'provincia_id' => $c['provincia_despues']]);
            }

            return $n;
        });

        $this->info("Corregidos: {$guardados} de ".count($cambios).'.');

        return self::SUCCESS;
    }

    /** @return array{regiones: array<int,string>, provincias: array<int,string>} */
    private function nombresGeografia(): array
    {
        return [
            'regiones' => Schema::hasTable('regiones') ? DB::table('regiones')->pluck('nombre', 'id')->all() : [],
            'provincias' => Schema::hasTable('provincias') ? DB::table('provincias')->pluck('nombre', 'id')->all() : [],
        ];
    }

    private function antesDespues(array $nombres, ?int $antes, ?int $despues): string
    {
        $etiqueta = fn (?int $id) => $id === null ? '—' : ($nombres[$id] ?? "#{$id}");

        return $antes === $despues ? $etiqueta($antes) : $etiqueta($antes).' → '.$etiqueta($despues);
    }

    /** Informativo: sin comuna no se puede decidir cuál dato manda, así que no se corrige. */
    private function mostrarSinComunaIncoherentes(array $nombres): void
    {
        if (! Schema::hasTable('provincias')) {
            return;
        }

        $filas = DB::table('clientes as cl')
            ->join('provincias as p', 'p.id', '=', 'cl.provincia_id')
            ->whereNull('cl.comuna_id')
            ->whereNotNull('cl.region_id')
            ->whereColumn('p.region_id', '!=', 'cl.region_id')
            ->orderBy('cl.id')
            ->get(['cl.id', 'cl.nombre_empresa', 'cl.region_id', 'cl.provincia_id']);

        if ($filas->isEmpty()) {
            return;
        }

        $this->warn("Sin comuna y con provincia de otra región ({$filas->count()}): revisar a mano, no se corrigen.");
        $this->table(
            ['ID', 'Cliente', 'Región', 'Provincia'],
            $filas->map(fn ($f) => [$f->id, mb_strimwidth((string) $f->nombre_empresa, 0, 34, '…'), $nombres['regiones'][$f->region_id] ?? "#{$f->region_id}", $nombres['provincias'][$f->provincia_id] ?? "#{$f->provincia_id}"])->all()
        );
    }
}
