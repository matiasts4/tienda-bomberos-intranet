<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro de auditoría: qué ejecutivo gestionó o modificó un contacto.
 */
class ContactoActividad extends Model
{
    use HasFactory;

    protected $table = 'contacto_actividades';

    public const ACCION_CREADO = 'creado';
    public const ACCION_ACTUALIZADO = 'actualizado';
    public const ACCION_ELIMINADO = 'eliminado';
    public const ACCION_VINCULOS = 'vinculos_actualizados';
    public const ACCION_GESTION = 'gestion';
    public const ACCION_CALIFICADO = 'calificado';

    /** Campos que nunca se guardan en el detalle de cambios. */
    private const CAMPOS_IGNORADOS = ['id', 'created_at', 'updated_at'];

    protected $fillable = [
        'contacto_id',
        'contacto_nombre',
        'user_id',
        'user_nombre',
        'accion',
        'descripcion',
        'cambios',
        'origen',
    ];

    protected $casts = [
        'cambios' => 'array',
    ];

    public function contacto()
    {
        return $this->belongsTo(Contacto::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Deja registro de una acción sobre un contacto. El ejecutivo se toma del
     * usuario autenticado salvo que se indique uno explícito.
     */
    public static function registrar(
        Contacto $contacto,
        string $accion,
        ?string $descripcion = null,
        ?array $cambios = null,
        ?string $origen = null,
        ?User $user = null
    ): self {
        $user = $user ?: auth()->user();

        return self::create([
            'contacto_id' => $contacto->id,
            'contacto_nombre' => $contacto->nombre,
            'user_id' => $user?->id,
            'user_nombre' => $user?->name,
            'accion' => $accion,
            'descripcion' => $descripcion,
            'cambios' => $cambios,
            'origen' => $origen,
        ]);
    }

    /**
     * Arma el detalle antes/después de los campos que cambiaron en el modelo.
     *
     * @return array<string, array{antes: mixed, despues: mixed}>
     */
    public static function diffDe(Contacto $contacto): array
    {
        $cambios = [];

        foreach ($contacto->getChanges() as $campo => $despues) {
            if (in_array($campo, self::CAMPOS_IGNORADOS, true)) {
                continue;
            }

            $cambios[$campo] = [
                'antes' => $contacto->getOriginal($campo),
                'despues' => $despues,
            ];
        }

        return $cambios;
    }
}
