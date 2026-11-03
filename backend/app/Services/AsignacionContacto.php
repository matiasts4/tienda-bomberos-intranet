<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\ContactoActividad;
use App\Models\Cotizacion;
use App\Models\Cuerpo;
use App\Models\Oportunidad;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Asignar un contacto a un Cuerpo, una Compañía o una empresa después de creado.
 *
 * Un contacto sin organización se cotiza como persona natural: sus leads y
 * cotizaciones apuntan solo a él. Al asignarlo, los que siguen abiertos pueden
 * pasar a la organización; las ventas y los cierres quedan como se emitieron.
 */
class AsignacionContacto
{
    public const TIPO_CUERPO = 'cuerpo';
    public const TIPO_COMPANIA = 'compania';
    public const TIPO_EMPRESA = 'empresa';

    public const TIPOS = [self::TIPO_CUERPO, self::TIPO_COMPANIA, self::TIPO_EMPRESA];

    /** Única etapa de cotización que se puede reasignar: las demás ya se vendieron, rechazaron o anularon. */
    private const COTIZACION_ABIERTA = 'cotización enviada';

    /**
     * Leads y cotizaciones a nombre del contacto que no apuntan a ninguna
     * organización, con si todavía se pueden pasar a una.
     *
     * @return array{oportunidades: list<array<string, mixed>>, cotizaciones: list<array<string, mixed>>}
     */
    public function registrosSinEntidad(Contacto $contacto): array
    {
        $oportunidades = Oportunidad::query()
            ->with('estado:id,nombre')
            ->where('contacto_id', $contacto->id)
            ->where(fn (Builder $q) => $this->sinEntidad($q))
            ->orderBy('id')
            ->get()
            ->map(fn (Oportunidad $o) => [
                'id' => $o->id,
                'codigo' => 'OP-' . str_pad((string) $o->id, 4, '0', STR_PAD_LEFT),
                'estado' => $o->estado?->nombre,
                'abierta' => $this->oportunidadAbierta($o->estado?->nombre),
            ]);

        $cotizaciones = Cotizacion::query()
            ->with('estado:id,nombre')
            ->where('contacto_id', $contacto->id)
            ->where(fn (Builder $q) => $this->sinEntidad($q))
            ->orderBy('id')
            ->get()
            ->map(fn (Cotizacion $c) => [
                'id' => $c->id,
                'codigo' => $c->numero ?: 'CT-' . str_pad((string) $c->id, 4, '0', STR_PAD_LEFT),
                'estado' => $c->estado?->nombre,
                'total' => (int) $c->total,
                'abierta' => mb_strtolower(trim((string) $c->estado?->nombre)) === self::COTIZACION_ABIERTA,
            ]);

        return [
            'oportunidades' => $oportunidades->values()->all(),
            'cotizaciones' => $cotizaciones->values()->all(),
        ];
    }

    /**
     * Deja al contacto en la organización y pasa a ella los leads y cotizaciones
     * pedidos, siempre que sigan sin organización y abiertos.
     *
     * @param  array<int, int>  $oportunidadIds
     * @param  array<int, int>  $cotizacionIds
     * @return array{oportunidades: list<int>, cotizaciones: list<int>}
     */
    public function asignar(Contacto $contacto, string $tipo, string $id, array $oportunidadIds = [], array $cotizacionIds = []): array
    {
        [$entidad, $nombre] = $this->destino($tipo, $id);

        return DB::transaction(function () use ($contacto, $entidad, $nombre, $oportunidadIds, $cotizacionIds) {
            $this->vincular($contacto, $entidad, 'contactos', reemplazarEmpresa: true, descripcion: 'Asignó el contacto a ' . $nombre);

            $registros = $this->registrosSinEntidad($contacto);
            $abiertos = fn (array $lista, array $pedidos) => collect($lista)
                ->filter(fn ($r) => $r['abierta'] && in_array($r['id'], array_map('intval', $pedidos), true))
                ->pluck('id')
                ->values()
                ->all();

            $oportunidades = $abiertos($registros['oportunidades'], $oportunidadIds);
            $cotizaciones = $abiertos($registros['cotizaciones'], $cotizacionIds);

            if ($oportunidades) {
                Oportunidad::whereKey($oportunidades)->update($entidad);
            }
            if ($cotizaciones) {
                Cotizacion::whereKey($cotizaciones)->update($entidad);
            }

            return ['oportunidades' => $oportunidades, 'cotizaciones' => $cotizaciones];
        });
    }

    /**
     * Agrega el contacto a la organización sin quitarle las que ya tiene. Quien es
     * de una Compañía es también de su Cuerpo (ver Compania::cuerpoIdsDe). La
     * empresa es una sola: solo se reemplaza si se pide (una asignación explícita);
     * desde un lead solo se completa si el contacto no tenía.
     *
     * @param  array{cliente_id?: ?string, cuerpo_id?: ?string, compania_id?: ?string}  $entidad
     * @return array<string, array{agregados: list<string>, quitados: list<string>}>
     */
    public function vincular(Contacto $contacto, array $entidad, string $origen, bool $reemplazarEmpresa = false, ?string $descripcion = null): array
    {
        $cuerpoId = $entidad['cuerpo_id'] ?? null;
        $companiaId = $entidad['compania_id'] ?? null;
        $clienteId = $entidad['cliente_id'] ?? null;
        $cambios = [];

        $cuerpoIds = array_values(array_unique(array_filter(array_merge(
            [$cuerpoId],
            Compania::cuerpoIdsDe([$companiaId]),
        ))));
        if ($cuerpoIds && $agregados = $contacto->cuerpos()->syncWithoutDetaching($cuerpoIds)['attached']) {
            $cambios['cuerpos'] = ['agregados' => array_values($agregados), 'quitados' => []];
        }
        if ($companiaId && $agregados = $contacto->companias()->syncWithoutDetaching([$companiaId])['attached']) {
            $cambios['companias'] = ['agregados' => array_values($agregados), 'quitados' => []];
        }

        $anterior = $contacto->cliente_id;
        $cambiaEmpresa = $clienteId && (string) $anterior !== (string) $clienteId && ($reemplazarEmpresa || !$anterior);
        if ($cambiaEmpresa && Cliente::whereKey($clienteId)->where('tipo', '!=', 'bomberos')->exists()) {
            // Sin evento: el cambio queda en la bitácora como vínculo, no como "datos editados".
            $contacto->updateQuietly(['cliente_id' => $clienteId]);
            $cambios['clientes'] = ['agregados' => [(string) $clienteId], 'quitados' => $anterior ? [(string) $anterior] : []];
        }

        if ($cambios) {
            ContactoActividad::registrar(
                $contacto,
                ContactoActividad::ACCION_VINCULOS,
                $descripcion ?? 'Vinculó el contacto desde un lead: ' . implode(', ', array_keys($cambios)),
                $cambios,
                $origen
            );
        }

        return $cambios;
    }

    /**
     * Columnas de lead/cotización para la organización elegida y su nombre legible.
     * Una Compañía lleva también su Cuerpo, igual que un lead calificado en Prospección.
     *
     * @return array{0: array{cliente_id: ?string, cuerpo_id: ?string, compania_id: ?string}, 1: string}
     */
    private function destino(string $tipo, string $id): array
    {
        $noExiste = fn (string $mensaje) => ValidationException::withMessages(['id' => [$mensaje]]);

        switch ($tipo) {
            case self::TIPO_CUERPO:
                $cuerpo = Cuerpo::find($id) ?? throw $noExiste('El Cuerpo no existe.');
                return [['cliente_id' => null, 'cuerpo_id' => (string) $cuerpo->id, 'compania_id' => null], 'Cuerpo de Bomberos de ' . $cuerpo->nombre];

            case self::TIPO_COMPANIA:
                $compania = Compania::with('cuerpo:id,nombre')->find($id) ?? throw $noExiste('La Compañía no existe.');
                $nombre = $compania->nombre . ($compania->cuerpo ? ' (' . $compania->cuerpo->nombre . ')' : '');
                return [['cliente_id' => null, 'cuerpo_id' => $compania->cuerpo_id ? (string) $compania->cuerpo_id : null, 'compania_id' => (string) $compania->id], $nombre];

            case self::TIPO_EMPRESA:
                // Las copias "bomberos" de Cuerpos/Compañías no son empresas: se asigna al Cuerpo o la Compañía.
                $cliente = Cliente::whereKey($id)->where('tipo', '!=', 'bomberos')->first() ?? throw $noExiste('La empresa no existe.');
                return [['cliente_id' => (string) $cliente->id, 'cuerpo_id' => null, 'compania_id' => null], $cliente->nombre_empresa];
        }

        throw ValidationException::withMessages(['tipo' => ['Tipo de organización no válido.']]);
    }

    private function sinEntidad(Builder $query): Builder
    {
        return $query->whereNull('cliente_id')->whereNull('cuerpo_id')->whereNull('compania_id');
    }

    /** Un lead sigue abierto mientras no esté cerrado (ganado o perdido). */
    private function oportunidadAbierta(?string $estado): bool
    {
        $estado = mb_strtolower(trim((string) $estado));

        return !str_starts_with($estado, 'cerrada') && !in_array($estado, ['perdida', 'ganada'], true);
    }
}
