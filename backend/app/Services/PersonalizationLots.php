<?php

namespace App\Services;

use App\Models\Personalizacion;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

/** Conserves quantities, design groups and attached files across splits/rework. */
class PersonalizationLots
{
    private static function key(array $value): string
    {
        return json_encode(Arr::sortRecursive($value), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function config($value): array
    {
        if (is_string($value)) $value = json_decode($value, true);
        abort_unless(is_array($value), 422, 'La configuración de la división no es válida.');
        Validator::make($value, [
            'grupos' => 'sometimes|array', 'grupos.*' => 'array',
            'grupos.*.tallas' => 'required|array|min:1',
            'grupos.*.tallas.*.cantidad' => 'required|integer|min:1',
        ])->validate();
        return $value;
    }

    private static function units(array $config): array
    {
        $units = [];
        foreach ($config['grupos'] ?? [] as $group) {
            $identity = Arr::except($group, ['tallas']);
            foreach ($group['tallas'] ?? [] as $size) {
                $key = self::key([$identity, Arr::except($size, ['cantidad'])]);
                $units[$key] = ($units[$key] ?? 0) + (int) $size['cantidad'];
            }
        }
        ksort($units);
        return $units;
    }

    public static function split(Personalizacion $item, int $remaining, int $extracted, $remainingConfig, $splitConfig): Personalizacion
    {
        abort_unless($remaining > 0 && $extracted > 0 && $remaining + $extracted === (int) $item->cantidad,
            422, 'Las cantidades deben sumar exactamente el total original y dejar unidades en ambos grupos.');
        $source = $item->configuracion ?? [];
        $sourceUnits = self::units($source);
        $variants = $item->variantes()->get();
        if ($sourceUnits) {
            $left = self::config($remainingConfig);
            $right = self::config($splitConfig);
            $leftUnits = self::units($left);
            $rightUnits = self::units($right);
            $combined = $leftUnits;
            foreach ($rightUnits as $key => $quantity) $combined[$key] = ($combined[$key] ?? 0) + $quantity;
            ksort($combined);
            abort_unless($sourceUnits === $combined && array_sum($leftUnits) === $remaining && array_sum($rightUnits) === $extracted,
                422, 'La división debe conservar cada talla, color, diseño y la cantidad original. Recarga el trabajo.');
            // A quantity-only selector cannot identify which people's garments failed QC.
            $leftGroups = array_map(fn ($g) => self::key(Arr::except($g, ['tallas'])), $left['grupos'] ?? []);
            foreach ($right['grupos'] ?? [] as $group) {
                $hasNames = collect($group['variables'] ?? [])->contains(fn ($v) => !empty($v['valor_variable']));
                $hasNames = $hasNames || (!empty($group['archivo_adjunto']) && collect($group['matrices'] ?? [])
                    ->contains(fn ($matrix) => ($matrix['tipo_contenido'] ?? '') === 'texto_variable'));
                abort_if($hasNames && in_array(self::key(Arr::except($group, ['tallas'])), $leftGroups, true), 422,
                    'Este grupo tiene nombres individuales. Divide grupos completos; una división parcial necesita identificar qué nombres corresponden a cada unidad.');
            }
            // The caller may partition sizes, never replace shared designs/assets.
            $left = array_replace($source, ['grupos' => $left['grupos']]);
            $right = array_replace($source, ['grupos' => $right['grupos']]);
        } else {
            abort_if($variants->count() > 1, 422, 'Falta el detalle de tallas para dividir este trabajo sin perder variantes.');
            $left = $right = $source;
        }
        $clone = $item->replicate()->unsetRelations();
        $item->cantidad = $remaining;
        $item->configuracion = self::summary($left);
        $item->save();
        $clone->cantidad = $extracted;
        $clone->configuracion = self::summary($right);
        $clone->save();
        foreach ($item->archivos()->get() as $file) {
            $copy = $file->replicate();
            $copy->personalizacion_id = $clone->id;
            $copy->save();
        }
        if ($sourceUnits) {
            self::syncVariants($item);
            self::syncVariants($clone);
        } elseif ($variants->count() === 1) {
            $variant = $variants->first();
            $copy = $variant->replicate();
            $variant->update(['cantidad' => $remaining]);
            $copy->personalizacion_id = $clone->id;
            $copy->cantidad = $extracted;
            $copy->save();
        }
        return $clone;
    }

    private static function summary(array $config): array
    {
        if (!isset($config['grupos'])) return $config;
        $sizes = [];
        foreach ($config['grupos'] as $group) foreach ($group['tallas'] ?? [] as $size) {
            $name = $size['talla'] ?? 'Genérico';
            $sizes[$name] = ($sizes[$name] ?? 0) + (int) $size['cantidad'];
        }
        $config['talla_resumen'] = implode(', ', array_map(fn ($size, $qty) => "$size ($qty)", array_keys($sizes), $sizes));
        return $config;
    }

    private static function syncVariants(Personalizacion $item): void
    {
        $rows = [];
        foreach ($item->configuracion['grupos'] ?? [] as $group) foreach ($group['tallas'] ?? [] as $size) {
            $identity = ['talla' => $size['talla'] ?? null, 'color' => $size['color'] ?? $group['color'] ?? null,
                'genero' => $size['genero'] ?? $group['genero'] ?? 'Unisex'];
            $key = self::key($identity);
            $rows[$key] ??= $identity + ['cantidad' => 0];
            $rows[$key]['cantidad'] += (int) $size['cantidad'];
        }
        $item->variantes()->delete();
        $item->variantes()->createMany(array_values($rows));
        $item->unsetRelation('variantes');
        // Resolve through the catalog relationship, not by guessing SKU digits.
        $generic = \App\Models\PteSkuGenerico::where('sku_base', $item->sku)->first()
            ?? \App\Models\PteSku::with('skuGenerico')->find($item->sku)?->skuGenerico;
        if ($generic) {
            $item->sku = $generic->sku_base;
            if (count($rows) === 1) {
                $identity = array_values($rows)[0];
                $matches = \App\Models\PteSku::where('sku_generico_id', $generic->id)
                    ->whereHas('color', fn ($query) => $query->where('nombre', $identity['color']))
                    ->whereHas('talla', fn ($query) => $query->where('nombre', $identity['talla']))->limit(2)->get();
                if ($matches->count() === 1) $item->sku = $matches->first()->sku;
            }
            $item->save();
        }
    }

    public static function merge(Personalizacion $target, Personalizacion $source): void
    {
        abort_if($target->id === $source->id, 422, 'Un trabajo no puede fusionarse consigo mismo.');
        $left = $target->configuracion ?? [];
        $right = $source->configuracion ?? [];
        abort_unless(self::key(Arr::except($left, ['grupos', 'talla_resumen'])) === self::key(Arr::except($right, ['grupos', 'talla_resumen'])),
            422, 'Los trabajos tienen diseños distintos. Consérvalos separados para no perder sus instrucciones.');
        $groups = [];
        foreach (array_merge($left['grupos'] ?? [], $right['grupos'] ?? []) as $group) {
            $key = self::key(Arr::except($group, ['tallas']));
            if (!isset($groups[$key])) {
                $groups[$key] = $group;
            } else {
                // Keep all units; each group retains its matrices and individual names.
                $groups[$key]['tallas'] = array_merge($groups[$key]['tallas'], $group['tallas']);
            }
        }
        foreach ($source->archivos()->get() as $file) {
            $existing = $target->archivos()->where('tipo_archivo', $file->tipo_archivo)->first();
            abort_if($existing && $existing->url_archivo !== $file->url_archivo, 422,
                'Los trabajos tienen archivos diferentes; no se fusionaron para evitar perderlos.');
            if (!$existing) {
                $copy = $file->replicate();
                $copy->personalizacion_id = $target->id;
                $copy->save();
            } else {
                $existing->update(['confirmado_fisico' => $existing->confirmado_fisico && $file->confirmado_fisico]);
            }
        }
        if ($groups) $target->configuracion = self::summary(array_replace($left, ['grupos' => array_values($groups)]));
        if ($groups && (self::units($left) || self::units($right))) {
            abort_unless(array_sum(self::units($left)) === (int) $target->cantidad
                && array_sum(self::units($right)) === (int) $source->cantidad, 422,
                'Los detalles de variantes no coinciden con el total. No se fusionó ningún trabajo.');
            self::syncVariants($target);
        } else {
            foreach ($source->variantes()->get() as $variant) {
                $existing = $target->variantes()->firstOrNew($variant->only(['talla', 'color', 'genero']));
                $existing->cantidad = ($existing->exists ? $existing->cantidad : 0) + $variant->cantidad;
                $existing->save();
            }
        }
        $target->cantidad += $source->cantidad;
        $target->notas = trim(($target->notas ?? '') . "\n[Reunificación #{$source->id}] " . ($source->notas ?? ''));
        $target->save();
        $source->delete(); // Keep the original row and its attachments available in the audit history.
    }

    public static function reunite(Personalizacion $item): ?Personalizacion
    {
        if (!$item->personalizacion_padre_id || $item->estado !== 'control_calidad') return null;
        $root = Personalizacion::lockForUpdate()->findOrFail($item->personalizacion_padre_id);
        $family = Personalizacion::where('personalizacion_padre_id', $root->id)
            ->orWhere('id', $root->id)->orderBy('id')->lockForUpdate()->get();
        if ($family->count() < 2 || $family->contains(fn ($member) => $member->estado !== 'control_calidad')) return null;
        foreach ($family as $member) {
            abort_unless($member->cotizacion_id === $root->cotizacion_id && $member->tipo === $root->tipo
                && $member->siguiente_proceso === $root->siguiente_proceso, 422, 'El reproceso pertenece a otro flujo; no se fusionó.');
            if ($member->id !== $root->id) self::merge($root, $member);
        }
        $root->bloqueado_qc = false;
        $root->personalizacion_padre_id = null;
        $root->ciclos_qc = $family->max('ciclos_qc');
        $root->save();
        return $root;
    }
}
