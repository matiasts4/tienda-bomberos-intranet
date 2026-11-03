<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes';

    // The primary key is now a string and not auto-incrementing
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', // Need to make it fillable since it's manually assigned
        'nombre_empresa',
        'tipo',
        'rut_empresa',
        'logo_url',
        'fecha_fundacion',
        'direccion',
        'giro',
        'telefono',
        'correo',
        'numero_companias',
        'fecha_ingreso',
        'region_id',
        'provincia_id',
        'comuna_id',
        'parent_id',
        'cuerpo_id',
        'compania_id',
    ];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function provincia()
    {
        return $this->belongsTo(Provincia::class);
    }

    public function comuna()
    {
        return $this->belongsTo(Comuna::class, 'comuna_id');
    }

    public function parent()
    {
        return $this->belongsTo(Cliente::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Cliente::class, 'parent_id');
    }

    public function contactos()
    {
        return $this->hasMany(Contacto::class);
    }

    public function cuerpo()
    {
        return $this->belongsTo(Cuerpo::class, 'cuerpo_id');
    }

    public function compania()
    {
        return $this->belongsTo(Compania::class, 'compania_id');
    }

    public function getLogoUrlAttribute($value)
    {
        if (!$value) {
            return null;
        }
        if (str_starts_with($value, 'http')) {
            return $value;
        }
        
        $path = $value;
        if (!str_starts_with($path, '/') && !str_starts_with($path, 'storage/')) {
            $path = '/storage/' . $path;
        } else if (str_starts_with($path, 'storage/')) {
            $path = '/' . $path;
        }

        return $path;
    }
}
