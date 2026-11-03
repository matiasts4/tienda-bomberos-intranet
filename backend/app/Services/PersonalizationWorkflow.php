<?php

namespace App\Services;

use App\Models\{Cotizacion, Despacho, Estado, Personalizacion};
use Illuminate\Support\Facades\Validator;

/** State changes run inside a transaction, always locking the quote before its jobs. */
class PersonalizationWorkflow
{
    public static function authorize(string $module = 'Personalización'): void
    {
        $user = auth()->user();
        abort_unless($user, 401);
        $roles = $module === 'Despacho' ? ['bodega'] : ['personalizacion'];
        abort_unless($user->role === 'superadmin' || (is_array($user->modulos)
            ? in_array($module, $user->modulos, true) : in_array($user->role, $roles, true)),
            403, "No tienes permiso para modificar el módulo {$module}.");
    }

    public static function lockItem(int $id): Personalizacion
    {
        $snapshot = Personalizacion::findOrFail($id);
        if ($snapshot->cotizacion_id) {
            Cotizacion::lockForUpdate()->findOrFail($snapshot->cotizacion_id);
        } else {
            Personalizacion::lockForUpdate()->findOrFail($snapshot->personalizacion_padre_id ?: $snapshot->id);
        }
        return Personalizacion::lockForUpdate()->findOrFail($id);
    }

    public static function transition(Personalizacion $item, array $data): Personalizacion
    {
        // Normalize old manual records when touched, without rewriting history in bulk.
        $item->tipo = ['embroidery' => 'bordado', 'print' => 'estampado', 'mixed' => 'mixto'][$item->tipo] ?? $item->tipo;
        if ($item->tipo === 'mixto' && !$item->siguiente_proceso) $item->siguiente_proceso = 'mixto_pendiente';
        Validator::make($data, [
            'estado' => 'sometimes|required|in:manual,pendiente_diseno,disenando,en_correccion,aprobacion,taller_por_hacer,taller_en_proceso,control_calidad,taller_completado,completado',
            'tipo' => 'sometimes|required|in:bordado,estampado,mixto',
            'notas' => 'nullable|string', 'prioridad' => 'sometimes|in:baja,media,alta,urgente',
            'configuracion' => 'sometimes|array', 'configuracion.grupos' => 'sometimes|array',
            'configuracion.grupos.*' => 'array', 'configuracion.grupos.*.tallas' => 'sometimes|array',
            'configuracion.grupos.*.tallas.*' => 'array',
        ])->validate();
        $old = $item->estado;
        $next = $data['estado'] ?? $old;
        if ($item->cotizacion_id && $next !== $old && in_array($next,
            ['taller_por_hacer', 'taller_en_proceso', 'control_calidad', 'taller_completado', 'completado'], true)) {
            QuotePersonalization::assertSale($item->cotizacion);
        }
        if (array_key_exists('configuracion', $data)) {
            // El origen comercial lo fija el servidor; editar el diseño no puede borrarlo ni reasignarlo.
            if (isset($item->configuracion['kit_origen'])) {
                $data['configuracion']['kit_origen'] = $item->configuracion['kit_origen'];
            } else {
                unset($data['configuracion']['kit_origen']);
            }
            // A slim board response must never erase commercial sizes/design groups.
            if (!array_key_exists('grupos', $data['configuracion']) && isset($item->configuracion['grupos'])) {
                $data['configuracion']['grupos'] = $item->configuracion['grupos'];
            }
            $sizes = collect($data['configuracion']['grupos'] ?? [])->flatMap(fn ($g) => $g['tallas'] ?? []);
            if ($sizes->isNotEmpty()) {
                abort_unless($sizes->every(fn ($s) => filter_var($s['cantidad'] ?? null, FILTER_VALIDATE_INT) !== false && $s['cantidad'] > 0)
                    && (int) $sizes->sum('cantidad') === (int) $item->cantidad, 422,
                    'El diseño debe conservar la cantidad del trabajo. Usa Dividir para separar unidades.');
            } else {
                abort_if(collect($item->configuracion['grupos'] ?? [])->flatMap(fn ($g) => $g['tallas'] ?? [])->isNotEmpty(),
                    422, 'No se pueden eliminar las tallas del pedido al guardar el diseño.');
            }
        }
        $edges = [
            'manual' => ['pendiente_diseno', 'disenando', 'aprobacion'],
            'pendiente_diseno' => ['disenando', 'aprobacion'],
            'disenando' => ['aprobacion', 'en_correccion'],
            'en_correccion' => ['disenando', 'aprobacion'],
            'aprobacion' => ['en_correccion', 'taller_por_hacer'],
            'taller_por_hacer' => ['taller_en_proceso'],
            // QC must go through qualityControl so failures cannot be bypassed.
            'taller_en_proceso' => [],
            'control_calidad' => ['taller_completado'],
            'taller_completado' => ['completado'], 'completado' => [],
        ];
        abort_if($next !== $old && !in_array($next, $edges[$old] ?? [], true), 422,
            'No se puede saltar etapas. Completa la etapa actual y el control de calidad antes de continuar.');
        abort_if($item->bloqueado_qc && $next !== $old, 422,
            'Hay unidades en reproceso. Espera a que todas regresen a Control de Calidad.');
        abort_if($old === 'completado' && array_intersect(array_keys($data), ['tipo', 'configuracion']),
            409, 'El trabajo ya está terminado; no se puede modificar su diseño o técnica.');
        if (isset($data['tipo']) && $data['tipo'] !== $item->tipo) {
            abort_unless($old === 'aprobacion' && $next === 'taller_por_hacer' && $item->tipo === 'mixto'
                && in_array($data['tipo'], ['bordado', 'estampado'], true), 422,
                'No se puede cambiar la técnica de un trabajo en curso.');
            // Derive the second technique on the server; never trust a missing/forged payload.
            $item->siguiente_proceso = $data['tipo'] === 'bordado' ? 'estampado' : 'bordado';
        }
        abort_if($next === 'taller_por_hacer' && ($data['tipo'] ?? $item->tipo) === 'mixto',
            422, 'Selecciona el primer taller del trabajo mixto.');
        if ($next !== $old) {
            $item->operario_asignado_id = auth()->id();
            if ($next === 'taller_en_proceso') $item->fecha_inicio_proceso = now();
        }
        $item->fill(array_intersect_key($data, array_flip(['estado', 'tipo', 'configuracion', 'notas', 'prioridad'])));
        if ($next === 'completado' && $item->siguiente_proceso) {
            $following = $item->siguiente_proceso === 'mixto_pendiente'
                ? ($item->tipo === 'estampado' ? 'bordado' : 'estampado') : $item->siguiente_proceso;
            abort_unless(in_array($following, ['bordado', 'estampado'], true) && $following !== $item->tipo,
                422, 'El segundo proceso no es válido. Revisa la configuración del trabajo mixto.');
            $item->proceso_previo = $item->tipo;
            $item->tipo = $following;
            $item->estado = 'aprobacion';
            $item->siguiente_proceso = null;
            $item->fecha_inicio_proceso = null;
            $item->fecha_inicio_qc = null;
        }
        $item->save();
        if ($item->estado === 'completado' && $item->cotizacion_id) {
            self::ensureDispatch(Cotizacion::findOrFail($item->cotizacion_id));
        }
        return $item;
    }

    public static function dispatchBlocker(Cotizacion $quote): ?string
    {
        if (!in_array($quote->estado?->nombre, ['Ventas', 'Aprobada por Cliente'], true))
            return 'La cotización todavía no está en Ventas.';
        $jobs = $quote->relationLoaded('personalizaciones') ? $quote->personalizaciones
            : Personalizacion::where('cotizacion_id', $quote->id)->get();
        if ($jobs->contains(fn ($job) => $job->estado !== 'completado' || $job->siguiente_proceso || $job->bloqueado_qc))
            return 'Faltan trabajos de personalización o reprocesos por terminar.';
        if ($jobs->isEmpty() && $quote->estado_personalizacion === 'sent_to_personalization')
            return 'La cotización fue enviada a taller pero no tiene trabajos activos. Revisa su historial.';
        foreach ($quote->detalles as $detail) {
            if (QuotePersonalization::informational($detail)) continue;
            if (KitPersonalization::isKit($detail)) {
                if (($detail->configuracion['kit_version'] ?? null) !== 1 || !$quote->personalizacion_completada) return 'Falta confirmar la composición y prendas del kit.';
                foreach (KitPersonalization::operationalItems($detail) as $child) {
                    if (QuotePersonalization::validateConfig($child, $child->configuracion, false) === 'none') continue;
                    $produced = $jobs->filter(fn ($job) => (int) ($job->configuracion['kit_origen']['item_id'] ?? 0) === (int) $detail->id && ($job->configuracion['kit_origen']['key'] ?? null) === $child->kit_key)->sum('cantidad');
                    if ((int) $produced !== (int) $child->cantidad) return 'Hay componentes del kit pendientes de conciliar con el taller.';
                }
                continue;
            }
            if (in_array($detail->tipo_personalizacion, [null, ''], true) && $detail->is_personalizable && !$quote->personalizacion_completada)
                return 'Falta confirmar si los productos requieren personalización o entrega directa.';
            if (in_array($detail->tipo_personalizacion, [null, '', 'none'], true)) continue;
            $produced = $jobs->filter(fn ($job) => (string) $job->numero_item === (string) $detail->n_item)->sum('cantidad');
            if ((int) $produced !== (int) $detail->cantidad)
                return 'Hay productos por configurar o cantidades de personalización pendientes de conciliar.';
        }
        return null;
    }

    public static function ensureDispatch(Cotizacion $quote): ?Despacho
    {
        if (self::dispatchBlocker($quote)) return null;
        $existing = Despacho::where('cotizacion_id', $quote->id)->first();
        if ($existing) return $existing; // Re-sync must not move a shipped order backwards.
        $initial = Estado::where('scope', 'despacho')->where('nombre', 'Por despachar')->first()
            ?? Estado::where('scope', 'despacho')->orderBy('orden')->first();
        abort_unless($initial, 422, 'Faltan los estados de Despacho. Configúralos antes de finalizar el trabajo.');
        return Despacho::create(['cotizacion_id' => $quote->id, 'cliente_id' => $quote->cliente_id, 'estado_id' => $initial->id]);
    }
}
