<?php

namespace App\Models;

use App\Observers\ContactoObserver;
use App\Support\NombreContactoCelular;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([ContactoObserver::class])]
class Contacto extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'cargo_id',
        'rut',
        'nombre',
        'telefono',
        'email',
        'cargo',
    ];

    /** NomContacCelu: requiere las relaciones de NombreContactoCelular::RELACIONES. */
    public function getNomContacCeluAttribute(): ?string
    {
        return NombreContactoCelular::para($this);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function cargo_obj()
    {
        return $this->belongsTo(Cargo::class, 'cargo_id');
    }

    public function cuerpos()
    {
        return $this->belongsToMany(Cuerpo::class, 'cuerpo_contacto', 'contacto_id', 'cuerpo_id');
    }

    public function companias()
    {
        return $this->belongsToMany(Compania::class, 'compania_contacto', 'contacto_id', 'compania_id');
    }

    /** Bitácora: qué ejecutivo gestionó o modificó este contacto. */
    public function actividades()
    {
        return $this->hasMany(ContactoActividad::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * Sin asignar: no cuelga de ningún Cuerpo, Compañía ni cliente (empresa).
     * `whereDoesntHave('cliente')` cubre también un cliente_id que ya no existe.
     */
    public function scopeSinAsignar(Builder $query): Builder
    {
        return $query->whereDoesntHave('cuerpos')
            ->whereDoesntHave('companias')
            ->whereDoesntHave('cliente');
    }

    /** De la Red de Bomberos: está en algún Cuerpo o Compañía. */
    public function scopeDeRedBomberos(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereHas('cuerpos')->orWhereHas('companias'));
    }

    /** Contacto de una empresa o particular (cliente suelto, no una copia "bomberos"). */
    public function scopeDeEmpresa(Builder $query): Builder
    {
        return $query->whereHas('cliente', fn (Builder $q) => $q->where('tipo', '!=', 'bomberos'));
    }
}
