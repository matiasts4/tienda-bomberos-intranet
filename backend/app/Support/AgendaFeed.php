<?php

namespace App\Support;

use App\Models\{Evento, Pago, User};
use Carbon\Carbon;
use Illuminate\Support\Collection;

/** Calendario y avisos comparten fuentes, permisos y fechas. */
class AgendaFeed
{
    public static function events(User $user, string $start, string $end, bool $personal = false, bool $overdue = false): Collection
    {
        $isSuperAdmin = !$personal && $user->role === 'superadmin';
        $ejecutivoId = $user->id;
        // Solo las fuentes con estado vigente admiten pendientes anteriores a hoy.
        $commitmentStart = $overdue ? '1970-01-01' : $start;
        // 1. Consultar eventos en BD
        $query = Evento::query()
            ->where(function ($q) use ($start, $end) {
                // Eventos normales
                $q->where(function($sub) use ($start, $end) {
                    $sub->where('inicio', '<', Carbon::parse($end)->startOfDay())
                        ->where(function ($s) use ($start) {
                            $s->where('fin', '>=', Carbon::parse($start)->startOfDay())
                                ->orWhere(fn ($q) => $q->whereNull('fin')->where('inicio', '>=', Carbon::parse($start)->startOfDay()));
                        });
                })
                // O eventos con recurrencia (siempre los traemos para expansión en frontend o backend posterior)
                ->orWhereNotNull('rrule');
            })
            ->with(['oportunidad.cliente:id,nombre_empresa', 'oportunidad.contacto:id,nombre', 'ejecutivo:id,name', 'user:id,name', 'eventable', 'invitados:id,name']);

        // Seguridad y niveles de visibilidad
        if (!$isSuperAdmin) {
            $query->where(function ($q) use ($ejecutivoId, $user) {
                $q->where('user_id', $ejecutivoId)
                    ->orWhere('ejecutivo_id', $ejecutivoId)
                    ->orWhere('nivel', 'corporativo')
                    ->orWhereHas('invitados', function ($sub) use ($ejecutivoId) {
                        $sub->where('users.id', $ejecutivoId);
                    })
                    ->orWhere(function ($sub) use ($user) {
                        $sub->where('nivel', 'area')
                            ->where('area', $user->role);
                    });
            });
        }

        $eventos = $query->get();

        // 2. Vencimientos de Cotizaciones (Virtuales)
        $cotQuery = AgendaCommitments::cotizaciones()
            ->with([
                'cuerpo:id,nombre',
                'compania:id,nombre,cuerpo_id',
                'compania.cuerpo:id,nombre',
                'oportunidad.cliente:id,nombre_empresa,telefono,correo',
                'oportunidad.contacto:id,nombre,telefono,email',
                'cliente:id,nombre_empresa,telefono,correo',
                'contacto:id,nombre,telefono,email',
                'ejecutivo:id,name',
                'creador:id,name',
                'estado:id,nombre'
            ]);

        if (!$isSuperAdmin) {
            $cotQuery->where('user_id', $ejecutivoId);
        }
        $cotizaciones = AgendaCommitments::enRango($cotQuery, $commitmentStart, $end, true);

        // 3. Llamadas de Prospección
        $prospQuery = AgendaCommitments::llamadas()
            ->where('proxima_llamada', '>=', $commitmentStart)->where('proxima_llamada', '<', $end)
            ->with(['contacto:id,nombre,telefono,cliente_id', 'contacto.cliente:id,nombre_empresa', 'user:id,name']);

        if (!$isSuperAdmin) {
            $prospQuery->where('user_id', $ejecutivoId);
        }
        $prospecciones = $prospQuery->get();

        // 4. Seguimientos de Oportunidad (Gestiones con fecha futura)
        $gestQuery = AgendaCommitments::seguimientos()
            ->where('fecha_vencimiento_nueva', '>=', $commitmentStart)->where('fecha_vencimiento_nueva', '<', $end)
            ->with(['oportunidad.cliente:id,nombre_empresa', 'oportunidad.contacto:id,nombre', 'user:id,name']);

        if (!$isSuperAdmin) {
            $gestQuery->where('user_id', $ejecutivoId);
        }
        $gestiones = $gestQuery->get();

        // 5. Pagos (Hitos financieros)
        $pagoQuery = Pago::query()
            ->where('fecha_pago', '>=', $start)->where('fecha_pago', '<', $end)
            ->with(['cotizacion.cliente:id,nombre_empresa', 'cotizacion.ejecutivo:id,name']);

        if (!$isSuperAdmin) {
            $pagoQuery->whereHas('cotizacion', fn ($q) => $q->where('user_id', $user->id));
        }
        $pagos = $pagoQuery->get();

        // 6. Formatear para FullCalendar
        $data = $eventos->map(function ($ev) use ($isSuperAdmin, $user) {
            $owner = $ev->ejecutivo ?? $ev->user;
            $color = $ev->color ?? match ($ev->tipo) {
                'reunion' => '#3b82f6',
                'seguimiento' => '#eab308',
                'cierre' => '#10b981',
                'vencimiento' => '#ef4444',
                'llamada' => '#06b6d4',
                default => match ($ev->nivel) {
                    'corporativo' => '#6366f1',
                    'area' => '#8b5cf6',
                    default => '#64748b'
                }
            };

            $titulo = ($ev->nivel === 'corporativo' ? "📢 " : ($ev->nivel === 'area' ? "🏢 " : "")) . $ev->titulo;
            if ($ev->oportunidad) {
                $nombreOpo = $ev->oportunidad->cliente?->nombre_empresa ?? $ev->oportunidad->contacto?->nombre ?? 'N/A';
                $titulo .= " ({$nombreOpo})";
            }
            else if ($ev->eventable_type) $titulo .= " [" . class_basename($ev->eventable_type) . "]";

            return [
                'id' => 'manual-' . $ev->id,
                'title' => $titulo,
                'start' => $ev->inicio?->toIso8601String(),
                'end' => $ev->fin?->toIso8601String(),
                'allDay' => (bool)$ev->is_all_day,
                'rrule' => $ev->rrule,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'extendedProps' => [
                    'descripcion' => $ev->descripcion,
                    'tipo' => $ev->tipo,
                    'nivel' => $ev->nivel ?? 'usuario',
                    'categoria' => $ev->categoria,
                    'oportunidad_id' => $ev->oportunidad_id,
                    'ejecutivo_nombre' => $owner?->name,
                    'responsable_id' => $owner?->id,
                    'responsable_nombre' => $owner?->name,
                    'creador_id' => $ev->user_id,
                    'creador_nombre' => $ev->user?->name,
                    'es_personal' => $ev->user_id === $user->id || $ev->ejecutivo_id === $user->id
                        || $ev->nivel === 'corporativo' || ($ev->nivel === 'area' && $ev->area === $user->role)
                        || $ev->invitados->contains('id', $user->id),
                    'eventable_id' => $ev->eventable_id,
                    'eventable_type' => $ev->eventable_type,
                    'invitados' => $ev->invitados,
                    'puede_editar' => $isSuperAdmin || $ev->user_id === $user->id || $ev->ejecutivo_id === $user->id,
                ]
            ];
        });

        $cotData = $cotizaciones->map(function ($cot) use ($user) {
            $esVenta = AgendaCommitments::esVenta($cot);
            $fecha = AgendaCommitments::fecha($cot);
            $dias = $fecha ? QuoteExpiry::diasParaVencer($fecha) : null;
            $entrega = !$fecha ? 'entrega pendiente de definir' : ($dias === 0 ? 'entrega hoy' : ($dias > 0 ? "entrega en {$dias} días" : 'entrega atrasada hace ' . abs($dias) . ' días'));
            $cliente = $cot->nombre_cliente ?: (
                $cot->cliente?->nombre_empresa ??
                $cot->oportunidad?->cliente?->nombre_empresa ??
                $cot->oportunidad?->contacto?->nombre ??
                '-'
            );
            $contacto = $cot->contacto ?? $cot->oportunidad?->contacto;
            $numero = !empty($cot->numero) ? $cot->numero : $cot->id;
            $telefono = $contacto?->telefono ?? $cot->cliente?->telefono ?? $cot->oportunidad?->cliente?->telefono;
            $email = $contacto?->email ?? $cot->cliente?->correo ?? $cot->oportunidad?->cliente?->correo;

            return [
                'id' => ($esVenta ? 'sale-' : 'quote-') . $cot->id,
                'title' => $esVenta ? ($fecha ? "Entrega VTA #{$numero} ({$cliente})" : "Entrega VTA pendiente de definir #{$numero} ({$cliente})") : "⏳ #{$numero} Vencimiento CT ({$cliente})",
                'start' => $fecha ? Carbon::parse($fecha)->startOfDay()->toIso8601String() : null,
                'allDay' => true,
                'backgroundColor' => $esVenta ? '#fb923c' : '#ef4444',
                'borderColor' => $esVenta ? '#fb923c' : '#ef4444',
                'textColor' => $esVenta ? '#431407' : '#ffffff',
                'extendedProps' => [
                    'tipo' => $esVenta ? 'entrega_venta' : 'vencimiento_automatico',
                    'cotizacion_id' => $cot->id,
                    'cotizacion_numero' => $numero,
                    'cotizacion_nombre' => $cot->nombre,
                    'cliente_nombre' => $cliente,
                    'contacto_nombre' => $contacto?->nombre,
                    'contacto_telefono' => $telefono,
                    'contacto_email' => $email,
                    'estado_nombre' => $cot->estado?->nombre,
                    'monto_total' => $cot->total,
                    'descripcion' => "Cotización #{$numero} " . ($esVenta ? $entrega : QuoteExpiry::describir($fecha)) . '.',
                    'dias_para_vencer' => $dias,
                    'fecha_vencimiento' => $esVenta ? null : $fecha,
                    'fecha_entrega' => $esVenta ? $fecha : null,
                    'entrega_pendiente' => $esVenta && !$fecha,
                    'fecha_venta' => $cot->fecha_venta?->toDateString(),
                    'plazo_produccion' => $cot->plazo_produccion ?: \App\Support\DeliveryDeadline::PLAZO_DEFAULT,
                    'ejecutivo_nombre' => $cot->ejecutivo?->name,
                    'responsable_id' => $cot->user_id,
                    'responsable_nombre' => $cot->ejecutivo?->name,
                    'creador_id' => $cot->creado_por_id,
                    'creador_nombre' => $cot->creador?->name,
                    'es_personal' => $cot->user_id === $user->id,
                ]
            ];
        });

        $prospData = $prospecciones->map(function ($p) use ($user) {
            $contacto = $p->contacto?->nombre ?? 'S/N';
            $empresa = $p->contacto?->cliente?->nombre_empresa;
            $tituloLlamada = "📞 Llamar: {$contacto}";
            if ($empresa) $tituloLlamada .= " ({$empresa})";
            if ($p->objetivo) $tituloLlamada .= " · {$p->objetivo}";
            // Con hora acordada es una cita a esa hora; sin hora, del día.
            $inicio = Carbon::parse($p->proxima_llamada)->startOfDay();
            if ($p->hora) {
                [$h, $m] = array_map('intval', explode(':', $p->hora));
                $inicio->setTime($h, $m);
            }
            return [
                'id' => 'prosp-' . $p->id,
                'title' => $tituloLlamada,
                'start' => $inicio->toIso8601String(),
                'allDay' => !$p->hora,
                'backgroundColor' => '#06b6d4', // Cyan
                'extendedProps' => [
                    'tipo' => 'prospeccion',
                    'notas' => $p->notas,
                    'objetivo' => $p->objetivo,
                    'nombre_contacto' => $contacto,
                    'telefono' => $p->contacto?->telefono,
                    'empresa' => $empresa,
                    'responsable_id' => $p->user_id,
                    'responsable_nombre' => $p->user?->name,
                    'es_personal' => $p->user_id === $user->id,
                ]
            ];
        });

        $gestData = $gestiones->map(function ($g) use ($user) {
            $cliente = $g->oportunidad->cliente?->nombre_empresa ?? $g->oportunidad->contacto?->nombre ?? 'S/N';
            return [
                'id' => 'gest-' . $g->id,
                'title' => "🔄 Seguimiento: {$cliente}",
                'start' => Carbon::parse($g->fecha_vencimiento_nueva)->startOfDay()->toIso8601String(),
                'allDay' => true,
                'backgroundColor' => '#f59e0b', // Amber
                'extendedProps' => [
                    'tipo' => 'oportunidad_seguimiento',
                    'glosa' => $g->glosa,
                    'responsable_id' => $g->user_id,
                    'responsable_nombre' => $g->user?->name,
                    'es_personal' => $g->user_id === $user->id,
                ]
            ];
        });

        $pagoData = $pagos->map(function ($p) use ($user) {
            $cliente = $p->cotizacion->cliente->nombre_empresa ?? 'S/N';
            return [
                'id' => 'pago-' . $p->id,
                'title' => "💰 Pago: #{$p->factura} ({$cliente})",
                'start' => Carbon::parse($p->fecha_pago)->startOfDay()->toIso8601String(),
                'allDay' => true,
                'backgroundColor' => '#8b5cf6', // Violet
                'extendedProps' => [
                    'tipo' => 'pago',
                    'monto' => $p->monto,
                    'factura' => $p->factura,
                    'responsable_id' => $p->cotizacion?->user_id,
                    'responsable_nombre' => $p->cotizacion?->ejecutivo?->name,
                    'es_personal' => $p->cotizacion?->user_id === $user->id,
                ]
            ];
        });

        $allData = $data->concat($cotData)->concat($prospData)->concat($gestData)->concat($pagoData);

        return $allData;
    }
}
