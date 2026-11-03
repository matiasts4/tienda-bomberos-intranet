<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MarketplaceLoginRequest;
use Illuminate\Http\Request;
use App\Models\Marketplace;
use App\Models\MktUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

class MarketplaceAuthController extends Controller
{
    /**
     * Authenticate a marketplace user (firefighter/client) or dealer via slug, rut/email and password.
     *
     * @param  \App\Http\Requests\MarketplaceLoginRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(MarketplaceLoginRequest $request)
    {
        $marketplace = Marketplace::where('slug', $request->slug)
                                  ->where('active', true)
                                  ->first();

        if (!$marketplace) {
            return response()->json([
                'message' => 'Portal de distribuidor no encontrado o inactivo'
            ], 404);
        }

        $inputIdentifier = trim($request->rut ?: $request->email ?: '');
        $user = null;

        if (!empty($inputIdentifier)) {
            $cleanRut = MktUser::cleanRut($inputIdentifier);
            $formattedRut = MktUser::formatRut($inputIdentifier);

            $user = MktUser::where('marketplace_id', $marketplace->id)
                           ->where('active', true)
                           ->where(function ($q) use ($cleanRut, $formattedRut, $inputIdentifier) {
                               $q->where('rut', $cleanRut)
                                 ->orWhere('rut', $formattedRut)
                                 ->orWhere('rut', $inputIdentifier)
                                 ->orWhere('email', $inputIdentifier);
                           })
                           ->first();

            if (!$user) {
                return response()->json([
                    'message' => 'RUT o correo no registrado en este portal. Contacta a tu administrador.'
                ], 401);
            }

            // Validar contraseña del usuario (soporta RUT con puntos/guión, clean, mayúscula o minúscula en K)
            $cleanInputPassword = MktUser::cleanRut($request->password);
            $lowerCleanInputPassword = strtolower($cleanInputPassword);
            $formattedInputPassword = MktUser::formatRut($request->password);
            $lowerFormattedInputPassword = strtolower($formattedInputPassword);

            $passwordMatches = Hash::check($request->password, $user->password) 
                || Hash::check($cleanInputPassword, $user->password)
                || Hash::check($lowerCleanInputPassword, $user->password)
                || Hash::check($formattedInputPassword, $user->password)
                || Hash::check($lowerFormattedInputPassword, $user->password);

            if (!$passwordMatches) {
                return response()->json([
                    'message' => 'Contraseña incorrecta. Recuerda que tu clave inicial es tu RUT.'
                ], 401);
            }
        } else {
            // Compatibilidad legacy de clave compartida de marketplace (si no ingresó RUT)
            if (!Hash::check($request->password, $marketplace->password)) {
                return response()->json([
                    'message' => 'Por favor ingresa tu RUT y contraseña para acceder.'
                ], 401);
            }
        }

        $payload = [
            'marketplace_id' => $marketplace->id,
            'user_id' => $user ? $user->id : null,
            'user_name' => $user ? $user->name : null,
            'user_rut' => $user ? $user->rut : null,
            'user_email' => $user ? $user->email : null,
            'user_phone' => $user ? $user->phone : null,
            'user_company_unit' => $user ? $user->company_unit : null,
            'user_cargo' => $user ? $user->cargo : null,
            'expires_at' => Carbon::now()->addHours(72)->timestamp
        ];

        $token = Crypt::encryptString(json_encode($payload));

        return response()->json([
            'token' => $token,
            'marketplace' => [
                'id' => $marketplace->id,
                'name' => $marketplace->name,
                'slug' => $marketplace->slug,
                'logo_url' => $marketplace->logo_url,
                'hero_image_url' => $marketplace->hero_image_url,
                'primary_color' => $marketplace->primary_color,
            ],
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'rut' => $user->rut,
                'email' => $user->email,
                'phone' => $user->phone,
                'company_unit' => $user->company_unit,
                'cargo' => $user->cargo,
            ] : null,
        ]);
    }

    /**
     * Get the authenticated marketplace and user information.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        $marketplace = $request->marketplace;
        $user = $request->user;

        return response()->json([
            'marketplace' => [
                'id' => $marketplace->id,
                'name' => $marketplace->name,
                'slug' => $marketplace->slug,
                'logo_url' => $marketplace->logo_url,
                'hero_image_url' => $marketplace->hero_image_url,
                'primary_color' => $marketplace->primary_color,
            ],
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'rut' => $user->rut,
                'email' => $user->email,
                'phone' => $user->phone,
                'company_unit' => $user->company_unit,
                'cargo' => $user->cargo,
                'is_admin' => isset($user->is_admin) ? (bool)$user->is_admin : false,
            ] : null,
        ]);
    }

    /**
     * Logout dealer (stateless token client cleanup confirmation).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        return response()->json([
            'message' => 'Sesión cerrada correctamente'
        ]);
    }
}
