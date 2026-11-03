<?php

namespace App\Http\Controllers;

use App\Models\Cargo;
use App\Models\Contacto;
use App\Models\Estado;
use App\Models\Oportunidad;
use App\Models\Cotizacion;
use App\Models\User;
use App\Rules\RutChileno;
use App\Services\AsignacionContacto;
use App\Services\TraspasoResponsableService;
use App\Support\QuoteExpiry;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OportunidadController extends Controller
{
    /**
     * GET /api/oportunidades
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $query = Oportunidad::query()
            ->with(['cliente', 'contacto', 'cuerpo', 'compania.cuerpo', 'estado', 'historial', 'ultima_gestion', ...Oportunidad::RELACIONES_RESPONSABLE])
            ->when($request->filled('estado_id'), fn($q) => $q->where('estado_id', $request->input('estado_id')))
            ->when($request->filled('cliente_id'), fn($q) => $q->where('cliente_id', $request->input('cliente_id')))
            ->when($request->filled('cuerpo_id'), function ($q) use ($request) {
                $cuerpoId = $request->input('cuerpo_id');
                $q->where(function ($query) use ($cuerpoId) {
                    $query->where('cuerpo_id', $cuerpoId)
                        ->orWhereHas('compania', function ($c) use ($cuerpoId) {
                            $c->where('cuerpo_id', $cuerpoId);
                        });
                });
            })
            ->when($request->filled('compania_id'), fn($q) => $q->where('compania_id', $request->input('compania_id')))
            ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->input('user_id')))
            ->when($request->boolean('exclude_has_quotation'), fn($q) => $q->doesntHave('cotizaciones'))
            ->when($request->filled('tipo_destinatario'), fn($q) => $q->deTipoDestinatario((string) $request->input('tipo_destinatario')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim((string) $request->query('q'));
                $q->whereHas('contacto', function ($qq) use ($term) {
                    $qq->where('nombre', 'like', "%{$term}%")
                       ->orWhere('telefono', 'like', "%{$term}%");
                })->orWhereHas('cliente', function ($qq) use ($term) {
                    $qq->where('nombre_empresa', 'like', "%{$term}%");
                })->orWhereHas('cuerpo', function ($qq) use ($term) {
                    $qq->where('nombre', 'like', "%{$term}%");
                })->orWhereHas('compania', function ($qq) use ($term) {
                    $qq->where('nombre', 'like', "%{$term}%");
                });
            });

        $query->orderByDesc('fecha_ingreso')->orderByDesc('id');

        return $query->paginate($perPage);
    }

    /**
     * POST /api/oportunidades
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'fecha_ingreso' => ['nullable', 'date'],
            'cliente_id' => ['nullable', 'string', 'max:15', 'exists:clientes,id'],
            'contacto_id' => ['nullable', 'integer', 'exists:contactos,id'],
            'cuerpo_id' => ['nullable', 'string', 'max:15', 'exists:cuerpos,id'],
            'compania_id' => ['nullable', 'string', 'max:15', 'exists:companias,id'],
            'referido_por' => ['nullable', 'string', 'max:150'],

            // Campos para creación de contacto manual si cliente_id está presente
            'nombre_contacto' => ['nullable', 'string', 'max:150'],
            'numero_contacto' => ['nullable', 'string', 'max:30'],
            'cargo_contacto' => ['nullable', 'string', 'max:150'],
            'email_contacto' => ['nullable', 'string', 'max:150', 'email'],
            'rut_contacto' => ['nullable', 'string', 'max:20', new RutChileno()],
            'cargo_id_contacto' => ['nullable', 'integer', 'exists:cargos,id'],
            'canal' => ['nullable', 'string', 'max:50'],

            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($q) {
                    $q->whereIn('role', ['ejecutivo', 'superadmin']);
                }),
            ],

            'estado_id' => [
                'required',
                'integer',
                Rule::exists('estados', 'id')->where(function ($q) {
                    $q->where('scope', 'oportunidad');
                }),
            ],
        ]);

        // Un lead tiene que ir dirigido a alguien: una organización o una persona.
        // Sin nada, la tarjeta queda como "Oportunidad #N" y su cotización sin cliente.
        $tieneDestinatario = collect(['cliente_id', 'contacto_id', 'cuerpo_id', 'compania_id'])
            ->contains(fn ($campo) => !empty($data[$campo]))
            || trim((string) ($data['nombre_contacto'] ?? '')) !== '';
        if (!$tieneDestinatario) {
            throw ValidationException::withMessages([
                'entidad' => ['Indica a quién va el lead: un Cuerpo, una Compañía, una empresa o una persona.'],
            ]);
        }

        if (empty($data['fecha_ingreso'])) {
            $data['fecha_ingreso'] = now()->toDateString();
        }

        // Quien la crea queda como creador; el responsable es el ejecutivo elegido (user_id).
        $data['creado_por_id'] = $request->user()?->id ?? $data['user_id'];

        // Contacto, lead y vínculo van juntos: si algo falla no queda un contacto suelto.
        $oportunidad = DB::transaction(function () use ($data) {
            // ✅ Persistir contacto manual si viene nombre_contacto sin contacto seleccionado
            $data['contacto_id'] = $this->resolveOrCreateContacto($data);

            // Remover campos que no pertenecen a la tabla oportunidades
            unset($data['nombre_contacto'], $data['numero_contacto'], $data['cargo_contacto'], $data['email_contacto'], $data['rut_contacto'], $data['cargo_id_contacto']);

            $oportunidad = Oportunidad::create($data);
            $this->vincularContactoAlLead($oportunidad);

            return $oportunidad;
        });
        $oportunidad->load(['cliente', 'estado', ...Oportunidad::RELACIONES_RESPONSABLE]);

        // Check if created with "Aprobada - Cotización" o "Cotización Enviada"
        $cotizacionId = null;
        if ($oportunidad->estado) {
            $estadoNombre = trim(mb_strtolower($oportunidad->estado->nombre));
            if ($estadoNombre === mb_strtolower('Aprobada - Cotización') || $estadoNombre === mb_strtolower('Cotización Enviada')) {
                $cotizacionId = $this->ensureCotizacion($oportunidad);
            }
        }

        // Return additional field
        $response = $oportunidad->toArray();
        if ($cotizacionId) {
            $response['cotizacion_id'] = $cotizacionId;
        }

        return response()->json($response, 201);
    }

    /**
     * GET /api/oportunidades/{oportunidad}
     */
    public function show(Oportunidad $oportunidad)
    {
        $oportunidad->load(['cliente', 'contacto', 'estado', 'historial', ...Oportunidad::RELACIONES_RESPONSABLE]);
        return response()->json($oportunidad);
    }

    /**
     * PATCH/PUT /api/oportunidades/{oportunidad}
     */
    public function update(Request $request, Oportunidad $oportunidad)
    {
        $data = $request->validate([
            'fecha_ingreso' => ['sometimes', 'nullable', 'date'],
            'referido_por' => ['sometimes', 'nullable', 'string', 'max:150'],
            'cliente_id' => ['sometimes', 'nullable', 'string', 'max:15', 'exists:clientes,id'],
            'contacto_id' => ['sometimes', 'nullable', 'integer', 'exists:contactos,id'],
            'cuerpo_id' => ['sometimes', 'nullable', 'string', 'max:15', 'exists:cuerpos,id'],
            'compania_id' => ['sometimes', 'nullable', 'string', 'max:15', 'exists:companias,id'],

            // Campos para creación de contacto manual
            'nombre_contacto' => ['sometimes', 'nullable', 'string', 'max:150'],
            'numero_contacto' => ['sometimes', 'nullable', 'string', 'max:30'],
            'cargo_contacto' => ['sometimes', 'nullable', 'string', 'max:150'],
            'email_contacto' => ['sometimes', 'nullable', 'string', 'max:150', 'email'],
            'rut_contacto' => ['sometimes', 'nullable', 'string', 'max:20', new RutChileno()],
            'cargo_id_contacto' => ['sometimes', 'nullable', 'integer', 'exists:cargos,id'],
            'canal' => ['sometimes', 'nullable', 'string', 'max:50'],

            'user_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($q) {
                    $q->whereIn('role', ['ejecutivo', 'superadmin']);
                }),
            ],

            'estado_id' => [
                'sometimes',
                'integer',
                Rule::exists('estados', 'id')->where(function ($q) {
                    $q->where('scope', 'oportunidad');
                }),
            ],
        ]);

        // Cambiar el responsable deja historial: se hace por el servicio de traspasos,
        // no escribiendo user_id directo.
        $nuevoResponsableId = $data['user_id'] ?? null;
        unset($data['user_id']);

        DB::transaction(function () use ($data, $oportunidad) {
            // ✅ Persistir contacto manual si viene nombre_contacto sin contacto seleccionado (igual que store)
            $nuevoContactoId = $this->resolveOrCreateContacto($data, $oportunidad);
            if ($nuevoContactoId !== null) {
                $data['contacto_id'] = $nuevoContactoId;
            }

            // Remover campos redundantes que ya no existen en la tabla
            unset($data['nombre_contacto'], $data['numero_contacto'], $data['cargo_contacto'], $data['email_contacto'], $data['rut_contacto'], $data['cargo_id_contacto']);

            $oportunidad->fill($data);

            // Si la edición toca a quién va dirigido, no puede dejarlo sin nadie
            // (un lead antiguo sin destinatario se puede seguir editando en lo demás).
            $tocaDestinatario = collect(['cliente_id', 'contacto_id', 'cuerpo_id', 'compania_id'])
                ->contains(fn ($campo) => array_key_exists($campo, $data));
            if ($tocaDestinatario && !$oportunidad->cliente_id && !$oportunidad->contacto_id && !$oportunidad->cuerpo_id && !$oportunidad->compania_id) {
                throw ValidationException::withMessages([
                    'entidad' => ['Indica a quién va el lead: un Cuerpo, una Compañía, una empresa o una persona.'],
                ]);
            }

            $oportunidad->save();
            $this->vincularContactoAlLead($oportunidad);
        });

        if ($nuevoResponsableId && (int) $nuevoResponsableId !== (int) $oportunidad->user_id) {
            $traspasos = app(TraspasoResponsableService::class);
            abort_unless(
                $request->user() && $traspasos->puedeTraspasar($request->user(), $oportunidad),
                403,
                'Solo el ejecutivo responsable o un administrador puede traspasar esta tarjeta.'
            );
            $traspasos->traspasar($oportunidad, User::findOrFail($nuevoResponsableId), $request->user());
        }

        // ✅ Log historial de modificación
        \App\Models\HistorialEtapa::create([
            'model_type' => Oportunidad::class,
            'model_id' => $oportunidad->id,
            'user_id' => ($request->user() ? $request->user()->id : 1),
            'estado_anterior_id' => $oportunidad->estado_id,
            'estado_nuevo_id' => $oportunidad->estado_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $oportunidad->load(['cliente', 'estado', ...Oportunidad::RELACIONES_RESPONSABLE]);
        return response()->json($oportunidad);
    }

    /**
     * DELETE /api/oportunidades/{oportunidad}
     */
    public function destroy(Oportunidad $oportunidad)
    {
        $oportunidad->delete();
        return response()->noContent();
    }

    /**
     * PATCH /api/oportunidades/{id}/estado
     * Cambiar estado de oportunidad + (gatillo) crear/actualizar cotización
     */
    public function updateEstado(Request $request, Oportunidad $oportunidad)
    {
        $request->validate([
            'estado_id' => ['required', 'integer', 'exists:estados,id'],
        ]);

        return DB::transaction(function () use ($request, $oportunidad) {
            $oportunidad = Oportunidad::lockForUpdate()->findOrFail($oportunidad->id);

            $estadoId = (int) $request->input('estado_id');
            $estado = Estado::findOrFail($estadoId);

            // (Opcional) validar scope oportunidad
            // if (($estado->scope ?? null) !== 'oportunidad') { ... }

            $estadoAnteriorId = $oportunidad->estado_id;
            $oportunidad->estado_id = $estadoId;
            $oportunidad->save();

            // Registrar Historial
            if ($estadoAnteriorId !== $estadoId) {
                \App\Models\HistorialEtapa::create([
                    'model_type' => Oportunidad::class,
                    'model_id' => $oportunidad->id,
                    'user_id' => $request->user() ? $request->user()->id : 1,
                    'estado_anterior_id' => $estadoAnteriorId,
                    'estado_nuevo_id' => $estadoId,
                ]);
            }

            // 🔥 Gatillo: cuando pasa a "Aprobada - Cotización" o "Cotización Enviada"
            $estadoNombre = trim(mb_strtolower($estado->nombre));
            $isAprobadaCot = $estadoNombre === mb_strtolower('Aprobada - Cotización') || $estadoNombre === mb_strtolower('Cotización Enviada') || $estadoNombre === mb_strtolower('enviar cotización');

            $cotizacionId = null;
            if ($isAprobadaCot) {
                // El responsable del lead no cambia al cotizar: la cotización lo hereda.
                // (Para cambiarlo existe el traspaso, que deja historial.)
                if (!$oportunidad->user_id && $request->user()) {
                    $oportunidad->user_id = $request->user()->id;
                    $oportunidad->save();
                }
                $cotizacionId = $this->ensureCotizacion($oportunidad);
            }

            return response()->json([
                'ok' => true,
                'oportunidad' => $oportunidad->fresh()->load(['cliente', 'estado', 'historial', ...Oportunidad::RELACIONES_RESPONSABLE]),
                'cotizacion_id' => $cotizacionId,
            ]);
        });
    }

    /**
     * El contacto de un lead es contacto de su organización: si el elegido (o el
     * recién creado) todavía no está en el Cuerpo/Compañía/empresa del lead, queda
     * vinculado y la bitácora del contacto lo registra. Un lead de persona natural
     * (sin organización) no vincula nada.
     */
    private function vincularContactoAlLead(Oportunidad $oportunidad): void
    {
        $entidad = $oportunidad->only(['cliente_id', 'cuerpo_id', 'compania_id']);
        if (!$oportunidad->contacto_id || !array_filter($entidad)) {
            return;
        }

        if ($contacto = Contacto::find($oportunidad->contacto_id)) {
            app(AsignacionContacto::class)->vincular($contacto, $entidad, 'oportunidades');
        }
    }

    /**
     * Crea un Contacto inline a partir de nombre_contacto/rut_contacto/etc. si no
     * viene un contacto_id ya elegido (lo vincula a la organización del lead
     * vincularContactoAlLead, después de guardar). Al editar, si el request no trae
     * la entidad se usa la que ya tiene la oportunidad. Si el RUT ya pertenece a un
     * contacto, se reutiliza ese contacto en vez de duplicarlo.
     */
    private function resolveOrCreateContacto(array $data, ?Oportunidad $oportunidad = null): ?int
    {
        if (!empty($data['contacto_id'])) {
            return $data['contacto_id'];
        }

        if (empty($data['nombre_contacto'])) {
            return null;
        }

        $clienteId = array_key_exists('cliente_id', $data) ? $data['cliente_id'] : $oportunidad?->cliente_id;

        $cargoId = $data['cargo_id_contacto'] ?? null;
        $cargo = $data['cargo_contacto'] ?? null;
        if ($cargoId && empty($cargo)) {
            $cargo = Cargo::find($cargoId)?->nombre;
        }

        $atributos = array_filter([
            'nombre' => $data['nombre_contacto'],
            'rut' => $data['rut_contacto'] ?? null,
            'cargo_id' => $cargoId,
            'cargo' => $cargo,
            'telefono' => $data['numero_contacto'] ?? null,
            'email' => $data['email_contacto'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $contacto = !empty($atributos['rut'])
            ? Contacto::where('rut', $atributos['rut'])->first()
            : null;

        if ($contacto) {
            $contacto->update($atributos);
        } else {
            $contacto = Contacto::create($atributos + ['cliente_id' => $clienteId]);
        }

        return $contacto->id;
    }

    private function ensureCotizacion(Oportunidad $oportunidad)
    {
        $existing = Cotizacion::where('oportunidad_id', $oportunidad->id)
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return $existing->id;
        }

        // ✅ Estado por defecto: "Cotización Enviada" (scope=cotizacion)
        $estadoDefaultId = Estado::where('scope', 'cotizacion')
            ->where('nombre', 'Cotización Enviada')
            ->value('id');

        if (!$estadoDefaultId) {
            // Fallback or error? throwing exception to rollback transaction if inside one
            throw new \Exception('No existe el estado "Cotización Enviada" con scope=cotizacion.');
        }

        $user = request()->user();

        $cot = Cotizacion::create([
            'oportunidad_id' => $oportunidad->id,
            'cliente_id' => $oportunidad->cliente_id,
            'contacto_id' => $oportunidad->contacto_id,
            'cuerpo_id' => $oportunidad->cuerpo_id,
            'compania_id' => $oportunidad->compania_id,
            // Responsable = el del lead (en 1ra instancia, el mismo que lo creó);
            // creador = quien la genera ahora.
            'user_id' => $oportunidad->user_id ?? $user?->id,
            'creado_por_id' => $user?->id ?? $oportunidad->user_id,
            'estado_id' => $estadoDefaultId,
            'fecha_creacion' => now()->toDateString(),
            'fecha_vencimiento' => QuoteExpiry::defaultFor(now()),
            'observaciones' => null,
            'origen' => 'oportunidades',
            'plazo_produccion' => '20 dias',
            'condiciones_pago' => 'Contado - Transferencia',
            'despacho' => 'Por pagar',
        ]);

        return $cot->id;
    }
}
