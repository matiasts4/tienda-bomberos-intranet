<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Comuna;
use App\Rules\RutChileno;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    /**
     * GET /api/clientes
     * Listar clientes (paginado + búsqueda)
     */
    public function index(Request $request)
    {
        \Illuminate\Support\Facades\Log::info("API Req", $request->all());
        \Illuminate\Support\Facades\DB::enableQueryLog();

        $query = Cliente::query()
            ->with(['region', 'provincia', 'comuna', 'children', 'parent']) // Eager load children count or children?
            ->when($request->filled('zona_id'), function ($q) use ($request) {
                $q->whereHas('region', function ($sq) use ($request) {
                    $sq->where('zona_id', $request->query('zona_id'));
                });
            })
            ->when($request->filled('region_id'), function ($q) use ($request) {
                $q->where('region_id', $request->query('region_id'));
            })
            ->when($request->filled('provincia_id'), function ($q) use ($request) {
                $q->where('provincia_id', $request->query('provincia_id'));
            })
            ->when($request->filled('tipo'), function ($q) use ($request) {
                $tipos = explode(',', $request->query('tipo'));
                $q->whereIn('tipo', $tipos);
            });

        // Hierarchy filters
        if ($request->boolean('root_only')) {
            $query->whereNull('parent_id');
        } elseif ($request->filled('parent_id')) {
            $query->where('parent_id', $request->query('parent_id'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            // El RUT se compara sin puntos ni guion, así "12345678" encuentra "12.345.678-9".
            $rutBuscado = preg_replace('/[^0-9kK]/', '', $search);

            $query->where(function ($q) use ($search, $rutBuscado) {
                $q->where('nombre_empresa', 'like', "%{$search}%")
                    ->orWhere('correo', 'like', "%{$search}%")
                    ->orWhere('telefono', 'like', "%{$search}%");

                if (strlen($rutBuscado) >= 4) {
                    $q->orWhereRaw("REPLACE(REPLACE(rut_empresa, '.', ''), '-', '') LIKE ?", ["%{$rutBuscado}%"]);
                }
            });
        }

        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(1, min($perPage, 500)); // Allow up to 500 for large lists

        $clientes = $query->orderBy('nombre_empresa')->paginate($perPage);

        \Illuminate\Support\Facades\Log::info("API SQL", \Illuminate\Support\Facades\DB::getQueryLog());

        return response()->json($clientes);
    }

    /**
     * POST /api/clientes
     * Crear cliente
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'string', 'max:15'], // Optional manual ID payload
            'nombre_empresa' => ['required', 'string', 'max:150'],
            'tipo' => ['nullable', 'string', 'in:bomberos,empresa,particular'],
            'giro' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:100'],
            'correo' => ['nullable', 'string', 'max:150'],
            'fecha_ingreso' => ['nullable', 'date'],
            'region_id' => ['nullable', 'exists:regiones,id'],
            'provincia_id' => ['nullable', 'exists:provincias,id'],
            'comuna_id' => ['nullable', 'exists:comunas,id'],
            'rut_empresa' => ['nullable', 'string', 'max:20', new RutChileno()],
            'fecha_fundacion' => ['nullable', 'date'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'numero_companias' => ['nullable', 'integer'],
            'parent_id' => ['nullable', 'exists:clientes,id'],
            'cuerpo_id' => ['nullable', 'exists:cuerpos,id'],
            'compania_id' => ['nullable', 'exists:companias,id'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'contacto' => ['nullable', 'array'],
            'contacto.nombre' => ['required_with:contacto', 'string', 'max:150'],
            'contacto.rut' => ['nullable', 'string', 'max:20'],
            'contacto.telefono' => ['nullable', 'string', 'max:50'],
            'contacto.email' => ['nullable', 'email', 'max:150'],
            'contacto.cargo' => ['nullable', 'string', 'max:100'],
            'contacto.cargo_id' => ['nullable', 'exists:cargos,id'],
        ]);

        $contacto = $data['contacto'] ?? null;
        unset($data['contacto']);

        $data = $this->completarUbicacionDesdeComuna($data);

        if (empty($data['fecha_ingreso'])) {
            $data['fecha_ingreso'] = now()->toDateString();
        }

        if (empty($data['tipo'])) {
            $data['tipo'] = (!empty($data['cuerpo_id']) || !empty($data['compania_id']) || !empty($data['parent_id']))
                ? 'bomberos'
                : 'empresa';
        }

        // Si no se envía un ID (por ejemplo, clientes normales), generamos uno secuencial manteniéndolo en el formato string.
        if (empty($data['id'])) {
            // Buscamos el ID numérico máximo que sea menor a 1,000,000 (para no chocar con los de bomberos que son 7+ dígitos)
            $maxId = \Illuminate\Support\Facades\DB::table('clientes')
                ->whereRaw('LENGTH(id) < 7')
                ->max(\Illuminate\Support\Facades\DB::raw('CAST(id AS UNSIGNED)'));

            $nextId = $maxId ? $maxId + 1 : 1;
            $data['id'] = (string) $nextId;
        }

        $cliente = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $contacto) {
            $cliente = Cliente::create($data);

            if ($contacto && !empty($contacto['nombre'])) {
                $cliente->contactos()->create($contacto);
            }

            return $cliente;
        });

        return response()->json($cliente->load('contactos'), 201);
    }

    /**
     * GET /api/clientes/{cliente}
     */
    public function show(Cliente $cliente)
    {
        return response()->json($cliente->load(['region', 'provincia', 'comuna', 'parent']));
    }

    /**
     * PATCH/PUT /api/clientes/{cliente}
     */
    public function update(Request $request, Cliente $cliente)
    {
        $data = $request->validate([
            'nombre_empresa' => ['sometimes', 'required', 'string', 'max:150'],
            'tipo' => ['sometimes', 'string', 'in:bomberos,empresa,particular'],
            'giro' => ['sometimes', 'nullable', 'string', 'max:255'],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:100'],
            'correo' => ['sometimes', 'nullable', 'string', 'max:150'],
            'fecha_ingreso' => ['sometimes', 'nullable', 'date'],
            'region_id' => ['nullable', 'exists:regiones,id'],
            'provincia_id' => ['nullable', 'exists:provincias,id'],
            'comuna_id' => ['nullable', 'exists:comunas,id'],
            'rut_empresa' => ['nullable', 'string', 'max:20', new RutChileno()],
            'fecha_fundacion' => ['nullable', 'date'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'numero_companias' => ['nullable', 'integer'],
            'parent_id' => ['nullable', 'exists:clientes,id'],
            'cuerpo_id' => ['nullable', 'exists:cuerpos,id'],
            'compania_id' => ['nullable', 'exists:companias,id'],
            'logo_url' => ['nullable', 'string', 'max:500'],
        ]);

        $cliente->update($this->completarUbicacionDesdeComuna($data));

        return response()->json($cliente->fresh()->load(['region', 'provincia', 'comuna']));
    }

    /**
     * DELETE /api/clientes/{cliente}
     */
    public function destroy(Cliente $cliente)
    {
        $cliente->delete();
        return response()->noContent();
    }

    /**
     * POST /api/clientes/{cliente}/logo
     * Subir el logo institucional
     */
    public function uploadLogo(Request $request, Cliente $cliente)
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'], // 5MB Max
        ]);

        try {
            $file = $request->file('image');
            $path = $file->store('clientes/logos_' . $cliente->id, 'public');

            $url = \Illuminate\Support\Facades\Storage::url($path);

            // Actualizar la URL en la BD
            $cliente->update(['logo_url' => $url]);

            return response()->json([
                'url' => $url,
                'message' => 'Logo actualizado correctamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al subir el logo'], 500);
        }
    }

    /**
     * Una comuna elegida define su región y provincia. Si el formulario manda la
     * comuna sin ellas (p. ej. se eligió primero la comuna), se completan acá para
     * que el cliente quede con la ubicación coherente; lo que sí venga se respeta.
     */
    private function completarUbicacionDesdeComuna(array $data): array
    {
        if (empty($data['comuna_id'])) {
            return $data;
        }

        $comuna = Comuna::find($data['comuna_id']);
        if (!$comuna) {
            return $data;
        }

        if (empty($data['region_id'])) {
            $data['region_id'] = $comuna->region_id;
        }
        if (empty($data['provincia_id']) && $comuna->provincia_id) {
            $data['provincia_id'] = $comuna->provincia_id;
        }

        return $data;
    }
}
