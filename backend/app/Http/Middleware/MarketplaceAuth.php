<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;
use App\Models\Marketplace;
use Carbon\Carbon;

class MarketplaceAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true);
            
            if (!$payload || !isset($payload['marketplace_id']) || !isset($payload['expires_at'])) {
                return response()->json(['message' => 'Invalid token'], 401);
            }

            if (Carbon::now()->timestamp > $payload['expires_at']) {
                return response()->json(['message' => 'Token expired'], 401);
            }

            $marketplace = Marketplace::where('id', $payload['marketplace_id'])
                                      ->where('active', true)
                                      ->first();

            if (!$marketplace) {
                return response()->json(['message' => 'Marketplace not found or inactive'], 401);
            }

            $user = null;
            if (isset($payload['user_id']) && $payload['user_id']) {
                $user = \App\Models\MktUser::where('id', $payload['user_id'])
                                           ->where('marketplace_id', $marketplace->id)
                                           ->where('active', true)
                                           ->first();

                if (!$user) {
                    return response()->json(['message' => 'Usuario inactivo o no autorizado'], 401);
                }
            }

            // Inject marketplace and user into request for controllers to use
            $request->merge(['marketplace' => $marketplace, 'user' => $user]);
            $request->marketplace = $marketplace;
            $request->user = $user;

            return $next($request);
        } catch (DecryptException $e) {
            // Permitir acceso transparente si es un usuario staff/admin con sesión activa en el ERP (Sanctum)
            $sanctumUser = auth('sanctum')->user() ?: \Laravel\Sanctum\PersonalAccessToken::findToken($token)?->tokenable;

            if ($sanctumUser) {
                $slug = $request->header('X-Marketplace-Slug') 
                     ?: $request->query('slug') 
                     ?: $request->input('slug');

                if (!$slug) {
                    return response()->json(['message' => 'Marketplace slug requerido para previsualización'], 400);
                }

                $marketplace = Marketplace::where('slug', $slug)
                                          ->where('active', true)
                                          ->first();

                if (!$marketplace) {
                    return response()->json(['message' => 'Marketplace no encontrado o inactivo'], 404);
                }

                $virtualUser = new \App\Models\MktUser([
                    'name' => $sanctumUser->name ?? $sanctumUser->email,
                    'rut' => $sanctumUser->rut ?? '',
                    'email' => $sanctumUser->email,
                    'phone' => $sanctumUser->telefono ?? '',
                    'company_unit' => 'Administración Central ERP',
                    'cargo' => 'Administrador ERP',
                ]);
                $virtualUser->id = $sanctumUser->id;
                $virtualUser->is_admin = true;

                $request->marketplace = $marketplace;
                $request->user = $virtualUser;
                $request->merge(['marketplace' => $marketplace, 'user' => $virtualUser]);

                return $next($request);
            }

            return response()->json(['message' => 'Invalid token format'], 401);
        }
    }
}
