<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Listar todos los usuarios
     */
    public function index()
    {
        $users = User::orderBy('name')->get();
        return response()->json($users);
    }

    /**
     * Crear un nuevo usuario
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'telefono' => 'nullable|string|max:20',
            'password' => 'required|string|min:6',
            'role' => 'required|string|in:superadmin,ejecutivo,fabrica,bodega,personalizacion', // Ajustar roles según necesites
            'modulos' => 'nullable|array',
            'modulos.*' => 'string',
            'codigo_vendedor' => ['nullable', 'string', 'regex:/^V[0-9]{1,2}$/', 'unique:users,codigo_vendedor'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'telefono' => $validated['telefono'] ?? null,
            'password' => bcrypt($validated['password']),
            'role' => $validated['role'],
            'modulos' => $validated['modulos'] ?? [],
            'codigo_vendedor' => ($validated['codigo_vendedor'] ?? null) ?: null,
        ]);

        return response()->json($user, 201);
    }

    /**
     * Mostrar un usuario específico
     */
    public function show(User $user)
    {
        return response()->json($user);
    }

    /**
     * Actualizar un usuario existente
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'telefono' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6',
            'role' => 'sometimes|required|string|in:superadmin,ejecutivo,fabrica,bodega,personalizacion',
            'modulos' => 'nullable|array',
            'modulos.*' => 'string',
            // V1..V4: va al final del nombre de contacto en el celular
            'codigo_vendedor' => ['nullable', 'string', 'regex:/^V[0-9]{1,2}$/', Rule::unique('users', 'codigo_vendedor')->ignore($user->id)],
        ]);

        $data = [
            'name' => $validated['name'] ?? $user->name,
            'email' => $validated['email'] ?? $user->email,
            'telefono' => $validated['telefono'] ?? $user->telefono,
            'role' => $validated['role'] ?? $user->role,
            'modulos' => $validated['modulos'] ?? $user->modulos,
            'codigo_vendedor' => array_key_exists('codigo_vendedor', $validated)
                ? ($validated['codigo_vendedor'] ?: null)
                : $user->codigo_vendedor,
        ];

        if (!empty($validated['password'])) {
            $data['password'] = bcrypt($validated['password']);
        }

        $user->update($data);

        return response()->json($user);
    }

    /**
     * Eliminar un usuario
     */
    public function destroy(User $user)
    {
        $user->delete();
        return response()->json(null, 204);
    }

    /**
     * Endpoint legacy para ejecutivos (podría eliminarse si se migra todo)
     */
    public function ejecutivos(Request $request)
    {
        $roles = ['ejecutivo', 'superadmin'];

        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'codigo_vendedor'])
            ->whereIn('role', $roles)
            ->orderBy('name')
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'nombre' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'codigo_vendedor' => $u->codigo_vendedor,
            ]);

        return response()->json($users);
    }
}
