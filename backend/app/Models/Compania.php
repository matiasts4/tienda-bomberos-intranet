<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Compania extends Model
{
    protected $table = 'companias';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'cuerpo_id',
        'nombre',
        'numero',
        'fecha_fundacion',
    ];

    protected $casts = [
        'fecha_fundacion' => 'date:Y-m-d',
    ];

    public function cuerpo()
    {
        return $this->belongsTo(Cuerpo::class, 'cuerpo_id');
    }

    public function clientes()
    {
        return $this->hasMany(Cliente::class, 'compania_id');
    }

    public function contactos()
    {
        return $this->belongsToMany(Contacto::class, 'compania_contacto', 'compania_id', 'contacto_id');
    }

    /**
     * Cuerpos de las compañías dadas. Quien es de una compañía es también de su
     * cuerpo: al vincular contactos se agregan estos cuerpos para que el vínculo
     * nunca falte (el árbol de prospección agrupa por cuerpo_contacto).
     *
     * @param  array<int, string>  $companiaIds
     * @return array<int, string>
     */
    public static function cuerpoIdsDe(array $companiaIds): array
    {
        $companiaIds = array_values(array_filter($companiaIds));
        if (empty($companiaIds)) {
            return [];
        }

        return static::whereIn('id', $companiaIds)
            ->whereNotNull('cuerpo_id')
            ->distinct()
            ->pluck('cuerpo_id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }
}
