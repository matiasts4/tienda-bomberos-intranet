<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\ContactoActividad;
use App\Models\Cuerpo;
use App\Rules\RutChileno;
use App\Services\AsignacionContacto;
use App\Support\NombreContactoCelular;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactoController extends Controller
{
    public function index(Cliente $cliente)
    {
        $contactos = $cliente->contactos()
            ->orderByRaw('cargo_id IS NULL, cargo_id ASC')
            ->orderBy('nombre', 'ASC')
            ->get();

        return response()->json($contactos);
    }

    /**
     * GET /api/contactos — listado general (búsqueda + paginado), para el
     * módulo de administración de clientes. No requiere una entidad padre.
     */
    public function indexAll(Request $request)
    {
        $perPage = (int) $request->query('per_page', 50);
        $perPage = max(1, min($perPage, 200));

        $query = Contacto::query()
            ->with([...NombreContactoCelular::RELACIONES, 'cliente'])
            ->orderBy('nombre');

        if ($request->filled('search')) {
            $term = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('nombre', 'like', $term)
                    ->orWhere('rut', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhereHas('cuerpos', fn ($cq) => $cq->where('nombre', 'like', $term))
                    ->orWhereHas('companias', fn ($cq) => $cq->where('nombre', 'like', $term));
            });
        }

        // A qué está asignado: sin_asignar | red_bomberos | empresa (cualquier otro valor no filtra).
        match ($request->query('asignacion')) {
            'sin_asignar' => $query->sinAsignar(),
            'red_bomberos' => $query->deRedBomberos(),
            'empresa' => $query->deEmpresa(),
            default => null,
        };

        $pagina = $query->paginate($perPage);
        $pagina->getCollection()->each->append('nom_contac_celu');

        return response()->json($pagina);
    }

    /**
     * GET /api/contactos/conteos — cuántos contactos hay por asignación, para
     * mostrar en el filtro cuántos quedan sin Cuerpo, Compañía ni empresa.
     */
    public function conteos()
    {
        return response()->json([
            'total' => Contacto::count(),
            'sin_asignar' => Contacto::sinAsignar()->count(),
            'red_bomberos' => Contacto::deRedBomberos()->count(),
            'empresa' => Contacto::deEmpresa()->count(),
        ]);
    }

    /**
     * GET /api/contactos/{contacto}/registros-sin-entidad — leads y cotizaciones a
     * nombre de la persona (sin organización) que se pueden pasar a la que se le asigne.
     */
    public function registrosSinEntidad(Contacto $contacto, AsignacionContacto $asignacion)
    {
        return response()->json($asignacion->registrosSinEntidad($contacto));
    }

    /**
     * POST /api/contactos/{contacto}/asignar — deja al contacto en un Cuerpo, una
     * Compañía o una empresa y pasa a ella los leads/cotizaciones abiertos elegidos.
     */
    public function asignar(Request $request, Contacto $contacto, AsignacionContacto $asignacion)
    {
        $data = $request->validate([
            'tipo' => ['required', Rule::in(AsignacionContacto::TIPOS)],
            'id' => ['required', 'string', 'max:15'],
            'oportunidad_ids' => ['nullable', 'array'],
            'oportunidad_ids.*' => ['integer'],
            'cotizacion_ids' => ['nullable', 'array'],
            'cotizacion_ids.*' => ['integer'],
        ]);

        $actualizados = $asignacion->asignar(
            $contacto,
            $data['tipo'],
            $data['id'],
            $data['oportunidad_ids'] ?? [],
            $data['cotizacion_ids'] ?? [],
        );

        return response()->json([
            'contacto' => $contacto->fresh()->load(['cargo_obj', 'cuerpos', 'companias.cuerpo', 'cliente']),
            'oportunidades_actualizadas' => $actualizados['oportunidades'],
            'cotizaciones_actualizadas' => $actualizados['cotizaciones'],
        ]);
    }

    /**
     * GET /api/cuerpos/{cuerpo}/contactos
     *
     * Con `solo_directos=1` trae solo a quienes no están en ninguna compañía del
     * cuerpo (comandancia, administración...). Sin él trae a todos, y en un cuerpo
     * grande eso son miles de contactos, porque cada contacto de una compañía
     * también es contacto de su cuerpo.
     */
    public function porCuerpo(Request $request, Cuerpo $cuerpo)
    {
        $contactos = $cuerpo->contactos()
            ->with(NombreContactoCelular::RELACIONES)
            ->when($request->boolean('solo_directos'), function ($query) use ($cuerpo) {
                $query->whereNotExists(function ($q) use ($cuerpo) {
                    $q->selectRaw('1')
                      ->from('compania_contacto as coc')
                      ->join('companias as co', 'co.id', '=', 'coc.compania_id')
                      ->whereColumn('coc.contacto_id', 'contactos.id')
                      ->where('co.cuerpo_id', $cuerpo->id);
                });
            })
            ->orderByRaw('cargo_id IS NULL, cargo_id ASC')
            ->orderBy('nombre', 'ASC')
            ->get()
            ->each->append('nom_contac_celu');

        return response()->json($contactos);
    }

    public function porCompania(Compania $compania)
    {
        $contactos = $compania->contactos()
            ->with(NombreContactoCelular::RELACIONES)
            ->orderByRaw('cargo_id IS NULL, cargo_id ASC')
            ->orderBy('nombre', 'ASC')
            ->get()
            ->each->append('nom_contac_celu');

        return response()->json($contactos);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => 'nullable|exists:clientes,id',
            'nombre' => 'required|string|max:255',
            'rut' => ['nullable', 'string', 'max:20', new RutChileno()],
            'telefono' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'cargo' => 'nullable|string|max:255',
            'cargo_id' => 'nullable|integer|exists:cargos,id',
            'cuerpo_ids' => 'nullable|array',
            'cuerpo_ids.*' => 'string|exists:cuerpos,id',
            'compania_ids' => 'nullable|array',
            'compania_ids.*' => 'string|exists:companias,id',
        ]);

        $contacto = Contacto::create(collect($validated)->except(['cuerpo_ids', 'compania_ids'])->all());
        $companiaIds = $validated['compania_ids'] ?? [];
        $cuerpos = $contacto->cuerpos()->sync($this->conCuerposDe($validated['cuerpo_ids'] ?? [], $companiaIds));
        $companias = $contacto->companias()->sync($companiaIds);
        $this->registrarVinculos($contacto, $cuerpos, $companias);

        return response()->json($contacto, 201);
    }

    public function update(Request $request, Contacto $contacto)
    {
        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'rut' => ['nullable', 'string', 'max:20', new RutChileno()],
            'telefono' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'cargo' => 'nullable|string|max:255',
            'cargo_id' => 'nullable|integer|exists:cargos,id',
            'cliente_id' => 'nullable|exists:clientes,id',
            'cuerpo_ids' => 'nullable|array',
            'cuerpo_ids.*' => 'string|exists:cuerpos,id',
            'compania_ids' => 'nullable|array',
            'compania_ids.*' => 'string|exists:companias,id',
        ]);

        $contacto->update(collect($validated)->except(['cuerpo_ids', 'compania_ids'])->all());

        $cuerpos = null;
        $companias = null;
        if (array_key_exists('cuerpo_ids', $validated) || array_key_exists('compania_ids', $validated)) {
            // Las compañías que quedan (las nuevas, o las de siempre si no vinieron)
            // fijan cuerpos que no se pueden quitar.
            $companiaIds = array_key_exists('compania_ids', $validated)
                ? ($validated['compania_ids'] ?? [])
                : $contacto->companias()->pluck('companias.id')->all();

            $cuerpos = array_key_exists('cuerpo_ids', $validated)
                ? $contacto->cuerpos()->sync($this->conCuerposDe($validated['cuerpo_ids'] ?? [], $companiaIds))
                : $contacto->cuerpos()->syncWithoutDetaching(Compania::cuerpoIdsDe($companiaIds));
            $companias = array_key_exists('compania_ids', $validated)
                ? $contacto->companias()->sync($companiaIds)
                : null;
        }
        $this->registrarVinculos($contacto, $cuerpos, $companias);

        return response()->json($contacto);
    }

    /**
     * GET /api/contactos/{contacto}/actividades — bitácora de un contacto:
     * quién lo creó, editó, eliminó, revinculó o gestionó. Solo administración.
     */
    public function actividades(Request $request, Contacto $contacto)
    {
        if ($respuesta = $this->soloAdmin($request)) {
            return $respuesta;
        }

        return response()->json(
            $contacto->actividades()->with('user:id,name')->get()
        );
    }

    /**
     * GET /api/contactos/actividades — feed global de gestión de contactos,
     * filtrable por ejecutivo, acción y rango de fechas.
     */
    public function actividadesGlobal(Request $request)
    {
        if ($respuesta = $this->soloAdmin($request)) {
            return $respuesta;
        }

        $perPage = (int) $request->query('per_page', 50);
        $perPage = max(1, min($perPage, 200));

        $query = ContactoActividad::query()
            ->with(['user:id,name', 'contacto:id,nombre'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->filled('contacto_id')) {
            $query->where('contacto_id', $request->query('contacto_id'));
        }
        if ($request->filled('user_id') && $request->query('user_id') !== 'all') {
            $query->where('user_id', $request->query('user_id'));
        }
        if ($request->filled('accion')) {
            $query->where('accion', $request->query('accion'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        return response()->json($query->paginate($perPage));
    }

    /**
     * La bitácora es información de auditoría: solo la ve la administración.
     */
    private function soloAdmin(Request $request)
    {
        if (in_array($request->user()?->role, ['superadmin', 'admin'], true)) {
            return null;
        }

        return response()->json(['message' => 'No autorizado.'], 403);
    }

    /**
     * Deja registro de los vínculos a cuerpos/compañías que se agregaron o
     * quitaron. Recibe el resultado de sync() (null si no se tocó la relación).
     */
    /**
     * Cuerpos pedidos más los de las compañías: quien es de una compañía es
     * también de su cuerpo (ver Compania::cuerpoIdsDe).
     *
     * @return array<int, string>
     */
    private function conCuerposDe(array $cuerpoIds, array $companiaIds): array
    {
        return array_values(array_unique(array_merge(
            array_map('strval', $cuerpoIds),
            Compania::cuerpoIdsDe($companiaIds),
        )));
    }

    private function registrarVinculos(Contacto $contacto, ?array $cuerpos, ?array $companias): void
    {
        $cambios = [];

        foreach (['cuerpos' => $cuerpos, 'companias' => $companias] as $relacion => $resultado) {
            if ($resultado === null) {
                continue;
            }

            $agregados = $resultado['attached'] ?? [];
            $quitados = $resultado['detached'] ?? [];

            if ($agregados || $quitados) {
                $cambios[$relacion] = [
                    'agregados' => array_values($agregados),
                    'quitados' => array_values($quitados),
                ];
            }
        }

        if (empty($cambios)) {
            return;
        }

        ContactoActividad::registrar(
            $contacto,
            ContactoActividad::ACCION_VINCULOS,
            'Actualizó vínculos: ' . implode(', ', array_keys($cambios)),
            $cambios,
            'contactos'
        );
    }

    public function destroy(Contacto $contacto)
    {
        $contacto->delete();
        return response()->json(null, 204);
    }
}