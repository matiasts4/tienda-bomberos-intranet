<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($data)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciales inválidas'],
            ]);
        }

        $request->session()->regenerate();

        // Generate Sanctum API token for cross-domain Bearer auth
        $user = Auth::user();
        $token = $user->createToken('app-token')->plainTextToken;

        return response()->json(['ok' => true, 'token' => $token]);
    }

    public function logout(Request $request)
    {
        // Revoke all tokens for this user
        if ($request->user()) {
            $request->user()->tokens()->delete();
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'telefono' => $user->telefono,
            'role' => $user->role,
            'modulos' => $user->modulos,
        ]);
    }

}
