<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class F_MpMateriaPrima extends Model
{
    use HasFactory;

    protected $table = 'mp_materias_primas';

    protected $fillable = [
        'tipo_material',
        'nombre',
        'unidad_medida',
        'requiere_especificacion',
        'ancho_estandar',
    ];

    protected $casts = [
        'requiere_especificacion' => 'boolean',
        'ancho_estandar' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con las especificaciones
     */
    public function especificaciones(): HasMany
    {
        return $this->hasMany(F_MpEspecificacion::class, 'materia_prima_id');
    }

    /**
     * Relación con los stocks
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(F_MpStock::class, 'materia_prima_id');
    }

    /**
     * Relación con las reparaciones
     */
    public function reparaciones(): HasMany
    {
        return $this->hasMany(F_Reparacion::class, 'materia_prima_id');
    }

    /**
     * Relación con las fichas técnicas
     */
    public function fichasTecnicas(): HasMany
    {
        return $this->hasMany(F_FichaTecnica::class, 'materia_prima_id');
    }
}
