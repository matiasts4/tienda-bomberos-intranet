<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\Oportunidad;
use App\Models\TraspasoResponsable;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Traspaso del ejecutivo responsable de una tarjeta comercial.
 *
 * El creador (`creado_por_id`) no se toca; solo cambia `user_id` y queda un
 * registro en `traspasos_responsable`.
 */
class TraspasoResponsableService
{
    /** Roles que pueden recibir una tarjeta (mismos que el selector de ejecutivos). */
    public const ROLES_DESTINO = ['ejecutivo', 'superadmin'];

    /** Roles que pueden traspasar tarjetas ajenas. */
    public const ROLES_SUPERVISION = ['admin', 'superadmin'];

    public function puedeTraspasar(User $actor, Oportunidad|Cotizacion $tarjeta): bool
    {
        return $tarjeta->user_id === null
            || (int) $tarjeta->user_id === (int) $actor->id
            || in_array($actor->role, self::ROLES_SUPERVISION, true);
    }

    public function traspasar(Oportunidad|Cotizacion $tarjeta, User $destino, User $actor, ?string $motivo = null): TraspasoResponsable
    {
        if (!in_array($destino->role, self::ROLES_DESTINO, true)) {
            throw ValidationException::withMessages([
                'user_id' => ['Solo se puede traspasar a un ejecutivo.'],
            ]);
        }

        return DB::transaction(function () use ($tarjeta, $destino, $actor, $motivo) {
            $actual = $tarjeta->newQuery()->lockForUpdate()->findOrFail($tarjeta->getKey());

            if ((int) $actual->user_id === (int) $destino->id) {
                throw ValidationException::withMessages([
                    'user_id' => ['Ese ejecutivo ya es el responsable.'],
                ]);
            }

            $traspaso = TraspasoResponsable::create([
                'model_type' => $tarjeta::class,
                'model_id' => $tarjeta->getKey(),
                'de_user_id' => $actual->user_id,
                'a_user_id' => $destino->id,
                'por_user_id' => $actor->id,
                'motivo' => $motivo !== null && trim($motivo) !== '' ? trim($motivo) : null,
            ]);

            $actual->user_id = $destino->id;
            $actual->save();
            $tarjeta->user_id = $destino->id;

            return $traspaso;
        });
    }
}
