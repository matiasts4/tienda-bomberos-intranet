<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cotizacion;
use App\Models\Evento;
use App\Models\Pago;
use App\Models\ProspeccionContacto;
use App\Models\User;
use App\Support\QuoteExpiry;
use App\Support\AgendaCommitments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SendVencimientoAlerts extends Command
{
    protected $signature = 'alerts:vencimientos';
    protected $description = 'Envía alertas de vencimiento a 3 días a cada ejecutivo';

    public function handle()
    {
        $configActiva = json_decode(DB::table('alerta_configs')
            ->where('clave', 'alerta_vencimientos')
            ->value('valor') ?? 'false');

        if (!$configActiva) {
            $this->info('Alertas de vencimiento desactivadas.');
            return;
        }

        $targetDate = Carbon::today()->addDays(3);
        $this->info("Buscando vencimientos para el: " . $targetDate->toDateString());

        // 1. Recolectar datos
        $items = collect();

        // Cotizaciones
        // Solo cotizaciones abiertas; sin número se muestra el ID, como en la agenda.
        $cotizaciones = AgendaCommitments::enRango(
            AgendaCommitments::cotizaciones()->whereNotNull('user_id')
                ->with(['estado', 'cuerpo', 'compania.cuerpo', 'cliente', 'contacto']),
            $targetDate->toDateString(), $targetDate->copy()->addDay()->toDateString()
        );
        foreach ($cotizaciones as $c) {
            $venta = AgendaCommitments::esVenta($c);
            $items->push([
                'user_id' => $c->user_id,
                'tipo' => $venta ? 'Entrega de venta' : 'Cotización',
                'titulo' => ($venta ? 'Entrega comprometida #' : 'Cotización #') . ($c->numero ?: $c->id),
                'descripcion' => ($venta ? 'Entrega el ' : 'Vence CT el ') . $targetDate->format('d/m/Y'),
                'referencia' => $c->nombre_cliente,
            ]);
        }

        // Eventos / Agenda
        Evento::whereDate('inicio', $targetDate)
            ->whereNotNull('user_id')
            ->get()
            ->each(fn($e) => $items->push([
                'user_id' => $e->user_id,
                'tipo' => 'Evento / Agenda',
                'titulo' => $e->titulo,
                'descripcion' => "Agendado para las " . $e->inicio->format('H:i'),
                'referencia' => $e->descripcion ?? 'Sin descripción'
            ]));

        // Pagos
        Pago::whereDate('fecha_pago', $targetDate)
            ->with('cotizacion')
            ->get()
            ->each(fn($p) => $items->push([
                'user_id' => $p->cotizacion?->user_id, // El dueño de la cotización
                'tipo' => 'Pago',
                'titulo' => "Pago Factura #{$p->factura}",
                'descripcion' => "Programado para el " . Carbon::parse($p->fecha_pago)->format('d/m/Y'),
                'referencia' => "$" . number_format($p->monto, 0, ',', '.')
            ]));

        // Prospección
        AgendaCommitments::llamadas()->whereDate('proxima_llamada', $targetDate)
            ->with('contacto')
            ->whereNotNull('user_id')
            ->get()
            ->each(fn($pc) => $items->push([
                'user_id' => $pc->user_id,
                'tipo' => 'Prospección',
                'titulo' => "Llamada a " . ($pc->contacto?->nombre ?? 'Contacto'),
                'descripcion' => "Programada para el " . $pc->proxima_llamada->format('d/m/Y'),
                'referencia' => $pc->notas ?? 'Sin notas'
            ]));

        // 2. Agrupar por Usuario y enviar
        $grouped = $items->filter(fn($i) => !is_null($i['user_id']))->groupBy('user_id');

        foreach ($grouped as $userId => $userItems) {
            $user = User::find($userId);
            if (!$user || !$user->email) continue;

            $this->info("Enviando reporte a: {$user->email} (" . $userItems->count() . " items)");

            try {
                Mail::send('emails.alerta_vencimientos', [
                    'user' => $user,
                    'items' => $userItems,
                    'fecha' => $targetDate->format('d/m/Y')
                ], function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject("🕒 Recordatorio: Vencimientos y entregas en 3 días - Tienda Bomberos");
                });
            } catch (\Exception $e) {
                $this->error("Error enviando a {$user->email}: " . $e->getMessage());
            }
        }

        $this->info('Proceso completado.');
    }
}
