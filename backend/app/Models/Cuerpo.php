<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cuerpo extends Model
{
    protected $table = 'cuerpos';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'region_id',
        'comuna_id',
        'ejecutivo_id',
        'nombre',
        'numero_socio',
        'rut',
        'fecha_fundacion',
        'direccion',
        'giro',
        'telefono',
        'superintendente',
        'comandante',
        'numero_companias',
        'logo_url',
    ];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    /** Cartera: ejecutivo a cargo del Cuerpo. */
    public function ejecutivo()
    {
        return $this->belongsTo(User::class, 'ejecutivo_id');
    }

    public function comuna()
    {
        return $this->belongsTo(Comuna::class, 'comuna_id');
    }

    public function companias()
    {
        return $this->hasMany(Compania::class, 'cuerpo_id');
    }

    public function clientes()
    {
        return $this->hasMany(Cliente::class, 'cuerpo_id');
    }

    public function contactos()
    {
        return $this->belongsToMany(Contacto::class, 'cuerpo_contacto', 'cuerpo_id', 'contacto_id');
    }
}
