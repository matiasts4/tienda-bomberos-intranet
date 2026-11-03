<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * A quién va dirigida una tarjeta comercial (oportunidad o cotización).
 *
 * No se guarda: se deduce de a qué apunta, así sirve también para lo histórico y
 * no puede quedar desalineado del cliente:
 *   - bomberos        Cuerpo o Compañía (o un cliente de tipo "bomberos")
 *   - empresa         cliente suelto de tipo "empresa"
 *   - persona_natural cliente suelto de tipo "particular", o solo un contacto sin
 *                     empresa, cuerpo ni compañía detrás
 */
trait TieneDestinatario
{
    public const DESTINATARIO_BOMBEROS = 'bomberos';
    public const DESTINATARIO_EMPRESA = 'empresa';
    public const DESTINATARIO_PERSONA_NATURAL = 'persona_natural';

    public const DESTINATARIOS = [
        self::DESTINATARIO_BOMBEROS,
        self::DESTINATARIO_EMPRESA,
        self::DESTINATARIO_PERSONA_NATURAL,
    ];

    public function getTipoDestinatarioAttribute(): string
    {
        if ($this->cuerpo_id || $this->compania_id) {
            return self::DESTINATARIO_BOMBEROS;
        }

        if ($this->cliente_id) {
            return match ($this->cliente?->tipo) {
                'bomberos' => self::DESTINATARIO_BOMBEROS,
                'particular' => self::DESTINATARIO_PERSONA_NATURAL,
                default => self::DESTINATARIO_EMPRESA,
            };
        }

        return $this->contacto_id
            ? self::DESTINATARIO_PERSONA_NATURAL
            : self::DESTINATARIO_EMPRESA;
    }

    /** Filtro equivalente al accessor, para listados. */
    public function scopeDeTipoDestinatario(Builder $query, string $tipo): Builder
    {
        $esDeBomberos = fn (Builder $q) => $q->whereNotNull('cuerpo_id')->orWhereNotNull('compania_id');
        $sinBomberos = fn (Builder $q) => $q->whereNull('cuerpo_id')->whereNull('compania_id');

        return match ($tipo) {
            self::DESTINATARIO_BOMBEROS => $query->where(function (Builder $q) use ($esDeBomberos, $sinBomberos) {
                $q->where($esDeBomberos)
                    ->orWhere(fn (Builder $w) => $w->where($sinBomberos)
                        ->whereHas('cliente', fn ($c) => $c->where('tipo', 'bomberos')));
            }),
            self::DESTINATARIO_PERSONA_NATURAL => $query->where($sinBomberos)->where(function (Builder $q) {
                $q->whereHas('cliente', fn ($c) => $c->where('tipo', 'particular'))
                    ->orWhere(fn (Builder $w) => $w->whereNull('cliente_id')->whereNotNull('contacto_id'));
            }),
            self::DESTINATARIO_EMPRESA => $query->where($sinBomberos)->where(function (Builder $q) {
                $q->whereHas('cliente', fn ($c) => $c->where(fn ($t) => $t->whereNotIn('tipo', ['bomberos', 'particular'])->orWhereNull('tipo')))
                    ->orWhere(fn (Builder $w) => $w->whereNull('cliente_id')->whereNull('contacto_id'));
            }),
            default => $query,
        };
    }
}
