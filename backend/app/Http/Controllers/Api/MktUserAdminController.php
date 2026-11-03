<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Marketplace;
use App\Models\MktUser;
use App\Imports\MktUserImport;
use App\Exports\MktUserExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MktUserAdminController extends Controller
{
    /**
     * List users for a specific marketplace with search and filters.
     */
    public function index(Request $request, $marketplaceId)
    {
        $marketplace = Marketplace::findOrFail($marketplaceId);
        $query = MktUser::where('marketplace_id', $marketplace->id);

        if ($request->filled('search')) {
            $search = trim($request->search);
            $cleanRut = MktUser::cleanRut($search);

            $query->where(function ($q) use ($search, $cleanRut) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('rut', 'like', "%{$search}%")
                  ->orWhere('rut', 'like', "%{$cleanRut}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('company_unit', 'like', "%{$search}%")
                  ->orWhere('cargo', 'like', "%{$search}%");
            });
        }

        if ($request->filled('active') && $request->active !== 'all') {
            $query->where('active', filter_var($request->active, FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = (int) $request->get('per_page', 50);
        $users = $query->orderBy('name')->paginate($perPage);

        // Métricas
        $totalCount = MktUser::where('marketplace_id', $marketplace->id)->count();
        $activeCount = MktUser::where('marketplace_id', $marketplace->id)->where('active', true)->count();
        $inactiveCount = $totalCount - $activeCount;
        $distinctUnitsCount = MktUser::where('marketplace_id', $marketplace->id)
                                    ->whereNotNull('company_unit')
                                    ->where('company_unit', '!=', '')
                                    ->distinct('company_unit')
                                    ->count('company_unit');

        return response()->json([
            'users' => $users,
            'marketplace' => [
                'id' => $marketplace->id,
                'name' => $marketplace->name,
                'slug' => $marketplace->slug,
                'logo_url' => $marketplace->logo_url,
            ],
            'stats' => [
                'total' => $totalCount,
                'active' => $activeCount,
                'inactive' => $inactiveCount,
                'units' => $distinctUnitsCount,
            ]
        ]);
    }

    /**
     * Store a newly created marketplace user.
     */
    public function store(Request $request, $marketplaceId)
    {
        $marketplace = Marketplace::findOrFail($marketplaceId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'rut' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company_unit' => 'nullable|string|max:255',
            'cargo' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:4',
            'active' => 'boolean',
        ]);

        $cleanRut = MktUser::cleanRut($validated['rut']);
        $formattedRut = MktUser::formatRut($validated['rut']);

        // Comprobar si ya existe
        $existing = MktUser::where('marketplace_id', $marketplace->id)
                           ->where(function ($q) use ($cleanRut, $formattedRut) {
                               $q->where('rut', $cleanRut)
                                 ->orWhere('rut', $formattedRut);
                           })
                           ->first();

        if ($existing) {
            return response()->json([
                'message' => "Ya existe un usuario registrado con el RUT {$formattedRut} en este marketplace."
            ], 422);
        }

        // Si no se proporcionó contraseña, la clave por defecto es el RUT limpio
        $rawPassword = !empty($validated['password']) ? $validated['password'] : $cleanRut;

        $user = MktUser::create([
            'marketplace_id' => $marketplace->id,
            'name' => $validated['name'],
            'rut' => $formattedRut,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'company_unit' => $validated['company_unit'] ?? null,
            'cargo' => $validated['cargo'] ?? null,
            'password' => Hash::make($rawPassword),
            'active' => $validated['active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Usuario creado exitosamente',
            'user' => $user,
        ], 201);
    }

    /**
     * Show a user.
     */
    public function show($id)
    {
        $user = MktUser::with('marketplace')->findOrFail($id);
        return response()->json($user);
    }

    /**
     * Update an existing user.
     */
    public function update(Request $request, $id)
    {
        $user = MktUser::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'rut' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company_unit' => 'nullable|string|max:255',
            'cargo' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:4',
            'active' => 'boolean',
        ]);

        $cleanRut = MktUser::cleanRut($validated['rut']);
        $formattedRut = MktUser::formatRut($validated['rut']);

        // Comprobar colisión de RUT con otro usuario
        $duplicate = MktUser::where('marketplace_id', $user->marketplace_id)
                            ->where('id', '!=', $user->id)
                            ->where(function ($q) use ($cleanRut, $formattedRut) {
                                $q->where('rut', $cleanRut)
                                  ->orWhere('rut', $formattedRut);
                            })
                            ->first();

        if ($duplicate) {
            return response()->json([
                'message' => "Otro usuario ya posee el RUT {$formattedRut} en este marketplace."
            ], 422);
        }

        $user->name = $validated['name'];
        $user->rut = $formattedRut;
        $user->email = $validated['email'] ?? null;
        $user->phone = $validated['phone'] ?? null;
        $user->company_unit = $validated['company_unit'] ?? null;
        $user->cargo = $validated['cargo'] ?? null;
        if (isset($validated['active'])) {
            $user->active = $validated['active'];
        }

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return response()->json([
            'message' => 'Usuario actualizado exitosamente',
            'user' => $user,
        ]);
    }

    /**
     * Toggle active state.
     */
    public function toggleActive($id)
    {
        $user = MktUser::findOrFail($id);
        $user->active = !$user->active;
        $user->save();

        return response()->json([
            'message' => $user->active ? 'Usuario activado' : 'Usuario desactivado',
            'active' => $user->active,
            'user' => $user,
        ]);
    }

    /**
     * Reset password to default (RUT).
     */
    public function resetPassword($id)
    {
        $user = MktUser::findOrFail($id);
        $cleanRut = MktUser::cleanRut($user->rut);

        $user->password = Hash::make($cleanRut);
        $user->save();

        return response()->json([
            'message' => "Contraseña restablecida exitosamente al RUT del usuario ({$cleanRut})",
        ]);
    }

    /**
     * Delete user.
     */
    public function destroy($id)
    {
        $user = MktUser::findOrFail($id);
        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado correctamente',
        ]);
    }

    /**
     * Import users from Excel/CSV file.
     */
    public function importExcel(Request $request, $marketplaceId)
    {
        $marketplace = Marketplace::findOrFail($marketplaceId);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt',
        ]);

        $file = $request->file('file');
        $import = new MktUserImport($marketplace->id);

        try {
            Excel::import($import, $file);

            return response()->json([
                'message' => "Importación completada: {$import->importedCount} creados, {$import->updatedCount} actualizados.",
                'imported_count' => $import->importedCount,
                'updated_count' => $import->updatedCount,
                'errors' => $import->errors,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error procesando el archivo: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Export marketplace users to Excel.
     */
    public function exportExcel($marketplaceId)
    {
        $marketplace = Marketplace::findOrFail($marketplaceId);
        $fileName = "usuarios_marketplace_{$marketplace->slug}_" . date('Ymd_His') . ".xlsx";

        return Excel::download(new MktUserExport($marketplace->id), $fileName);
    }

    /**
     * Download template for importing users.
     */
    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla_usuarios_marketplace.csv"',
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM para Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Cabeceras
            fputcsv($handle, ['Nombre', 'RUT', 'Email', 'Telefono', 'Compania', 'Cargo', 'Activo'], ';');
            
            // Fila de ejemplo
            fputcsv($handle, ['Juan Perez Gonzalez', '12.345.678-9', 'juan.perez@bomberos.cl', '+56912345678', '1ra Compania San Clemente', 'Voluntario', '1'], ';');
            fputcsv($handle, ['Maria Tapia Silva', '18.765.432-1', 'maria.tapia@bomberos.cl', '+56987654321', 'Comandancia', 'Capitan', '1'], ';');
            
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
