<?php

namespace App\Console\Commands;

use App\Models\Cotizacion;
use App\Support\QuoteExpiry;
use Illuminate\Console\Command;

/**
 * Completa fecha_vencimiento en cotizaciones abiertas que nacieron sin ella
 * (creadas desde Oportunidades). Solo llena fechas nulas con la regla
 * creación + 15 días hábiles; no toca cerradas ni fechas existentes.
 * Sin --apply solo muestra lo que haría.
 */
class CompletarVencimientoCotizacionesCommand extends Command
{
    protected $signature = 'cotizaciones:completar-vencimiento
        {--apply : Guarda las fechas (sin esta opción solo se informa)}';

    protected $description = 'Completa la fecha de vencimiento de cotizaciones abiertas que no la tienen';

    public function handle(): int
    {
        $cotizaciones = QuoteExpiry::abiertas(Cotizacion::query())
            ->whereNull('fecha_vencimiento')
            ->with('estado:id,nombre')
            ->orderBy('id')
            ->get();

        if ($cotizaciones->isEmpty()) {
            $this->info('No hay cotizaciones abiertas sin fecha de vencimiento.');

            return self::SUCCESS;
        }

        $filas = $cotizaciones->map(function (Cotizacion $c) {
            $base = $c->fecha_creacion ?? $c->created_at;

            return [
                'cotizacion' => $c,
                'nueva' => $base ? QuoteExpiry::defaultFor($base) : null,
                'base' => $base ? $base->toDateString() : null,
            ];
        });

        $this->table(
            ['ID', 'Estado', 'Creación', 'Vencimiento nuevo'],
            $filas->map(fn ($f) => [
                $f['cotizacion']->id,
                $f['cotizacion']->estado?->nombre,
                $f['base'] ?? '—',
                $f['nueva'] ?? 'sin fecha de creación: se omite',
            ])->all()
        );

        $aplicables = $filas->filter(fn ($f) => $f['nueva'] !== null);

        if (! $this->option('apply')) {
            $this->info("Simulacro: {$aplicables->count()} cotizaciones se actualizarían. Ejecuta con --apply para guardar.");

            return self::SUCCESS;
        }

        foreach ($aplicables as $f) {
            // Solo si sigue nula: no pisar una fecha puesta mientras corría el comando.
            Cotizacion::whereKey($f['cotizacion']->id)
                ->whereNull('fecha_vencimiento')
                ->update(['fecha_vencimiento' => $f['nueva']]);
        }

        $this->info("Actualizadas {$aplicables->count()} cotizaciones.");

        return self::SUCCESS;
    }
}
