<?php

namespace App\Services;

use App\Models\Contacto;
use App\Models\Cotizacion;
use App\Models\Peticion;
use Illuminate\Database\Eloquent\Builder;

final class SurveyQuoteSource
{
    public static function externalQuery(): Builder
    {
        return Peticion::query()->whereNull('cotizacion_id')->whereNotNull('referencia_externa')->where('referencia_externa', '!=', '');
    }

    public static function externalGroups(): Builder
    {
        return self::externalQuery()->selectRaw('MIN(id) as id')->groupBy('referencia_externa', 'user_id', 'cliente_externo');
    }

    public static function resolve(string $source, int $id, bool $lock = false): array
    {
        if ($source === 'sistema') {
            $query = Cotizacion::with(['cliente', 'contacto', 'cuerpo', 'compania.cuerpo', 'user']);
            $quote = ($lock ? $query->lockForUpdate() : $query)->findOrFail($id);

            return self::describeInternal($quote);
        }

        $selected = self::externalQuery()->findOrFail($id);
        // La cotización externa se identifica por referencia + vendedor + cliente.
        $query = self::externalQuery()->with('user')->where('referencia_externa', $selected->referencia_externa)
            ->where('user_id', $selected->user_id)->where('cliente_externo', $selected->cliente_externo)->orderBy('id');
        $quote = ($lock ? $query->lockForUpdate() : $query)->firstOrFail();

        return self::describeExternal($quote);
    }

    public static function describeInternal(Cotizacion $quote): array
    {
        return [
            'source' => 'sistema', 'source_id' => (int) $quote->id, 'key' => 'sistema:'.$quote->id,
            'cotizacion_id' => $quote->id, 'peticion_id' => null,
            'referencia' => (string) ($quote->numero ?: $quote->id), 'nombre_venta' => $quote->nombre,
            'cliente_id' => $quote->cliente_id, 'contacto_id' => $quote->contacto_id,
            'cliente_nombre' => $quote->contacto?->nombre ?: $quote->cliente?->nombre_contacto,
            'empresa_cliente' => ($quote->cuerpo || $quote->compania?->cuerpo)
                ? $quote->nombre_cliente : $quote->cliente?->nombre_empresa,
            'vendedor_id' => $quote->user_id, 'vendedor_nombre' => $quote->user?->name,
        ];
    }

    public static function describeExternal(Peticion $quote): array
    {
        return [
            'source' => 'externa', 'source_id' => (int) $quote->id, 'key' => 'externa:'.$quote->id,
            'cotizacion_id' => null, 'peticion_id' => $quote->id,
            'referencia' => $quote->referencia_externa, 'nombre_venta' => $quote->nombre_cotizacion,
            'cliente_id' => null, 'contacto_id' => null, 'cliente_nombre' => $quote->cliente_externo,
            'empresa_cliente' => $quote->cliente_externo,
            'vendedor_id' => $quote->user_id, 'vendedor_nombre' => $quote->user?->name,
        ];
    }

    public static function contact(int $id): array
    {
        $contact = Contacto::with(['cliente', 'cuerpos', 'companias.cuerpo'])->findOrFail($id);

        return self::describeContact($contact);
    }

    public static function describeContact(Contacto $contact): array
    {
        $company = $contact->companias->first();

        return [
            'id' => $contact->id, 'nombre' => $contact->nombre, 'cargo' => $contact->cargo,
            'cliente_id' => $contact->cliente_id,
            'empresa' => $company ? trim(($company->cuerpo?->nombre ?? '').' — '.$company->nombre, ' —')
                : ($contact->cuerpos->first()?->nombre ?: $contact->cliente?->nombre_empresa),
        ];
    }
}
