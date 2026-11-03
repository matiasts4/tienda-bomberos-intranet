<?php

namespace App\Http\Controllers;

use App\Support\AgendaFeed;
use App\Support\AgendaNoticeReceipts;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class AgendaAvisoController extends Controller
{
    public function index(Request $request)
    {
        $day = CarbonImmutable::now('America/Santiago')->startOfDay();
        $events = AgendaFeed::events($request->user(), $day->toDateString(), $day->addDays(4)->toDateString(), true, true)
            // Un pago registrado es historial, no una deuda pendiente.
            ->reject(fn ($event) => $event['extendedProps']['tipo'] === 'pago')
            ->map(function ($event) {
                $event['fingerprint'] = hash('sha256', json_encode($event, JSON_UNESCAPED_UNICODE));
                return $event;
            })->values();

        return response()->json([
            'day' => $day->toDateString(),
            'end' => $day->addDays(4)->toDateString(),
            'timezone' => 'America/Santiago',
            'events' => $events,
            'receipts' => (new AgendaNoticeReceipts)->read($request->user()->id, $day->toDateString()),
        ]);
    }

    /** Reserva atómica: dos pestañas/dispositivos no muestran el mismo aviso. */
    public function register(Request $request)
    {
        $data = $request->validate([
            'day' => 'required|date_format:Y-m-d',
            'mode' => 'required|in:shown,read',
            'keys' => 'required|array|min:1|max:200',
            'keys.*' => ['required', 'string', 'distinct', 'max:191', 'regex:/^(manual|quote|sale|prosp|gest)-[0-9]+:[0-9]{4}-[0-9]{2}-[0-9]{2}:[a-f0-9]{64}$/'],
        ]);
        $now = CarbonImmutable::now('America/Santiago');
        // La respuesta que cruzó medianoche debe volver a consultar antes de avisar.
        if ($data['day'] !== $now->toDateString()) {
            return response()->json(['message' => 'El día cambió. Actualiza los avisos.'], 409);
        }

        $accepted = (new AgendaNoticeReceipts)->register($request->user()->id, $data['day'], $data['mode'], $data['keys'], $now);

        return response()->json(['accepted' => $accepted]);
    }
}
