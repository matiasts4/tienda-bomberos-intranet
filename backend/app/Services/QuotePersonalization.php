<?php

namespace App\Services;

use App\Models\{Cotizacion, DetalleCotizacion, Personalizacion, PteSku, PteSkuGenerico};
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class QuotePersonalization
{
    public static function authorize(): void
    {
        $user = auth()->user();
        abort_unless($user, 401);
        // El tablero es compartido: conservar acceso por módulo, no por propietario.
        $allowed = $user->role === 'superadmin'
            || (is_array($user->modulos)
                ? count(array_intersect(['Comercial', 'Personalización'], $user->modulos)) > 0
                : in_array($user->role, ['ejecutivo', 'personalizacion'], true));
        abort_unless($allowed, 403, 'No tienes acceso a la configuración de personalización.');
    }

    public static function generic(DetalleCotizacion $item): ?PteSkuGenerico
    {
        if ($item->relationLoaded('personalizationGeneric')) return $item->getRelation('personalizationGeneric');
        if (!$item->sku) return null;
        $generic = PteSkuGenerico::where('sku_base', $item->sku)->first()
            ?? PteSku::with('skuGenerico')->find($item->sku)?->skuGenerico;
        $item->setRelation('personalizationGeneric', $generic);
        return $generic;
    }

    public static function informational(DetalleCotizacion $item): bool
    {
        return (bool) (self::generic($item)?->es_informativo_cotizacion ?? false);
    }

    public static function primeGenerics(\Illuminate\Support\Collection $items): void
    {
        $skus = $items->pluck('sku')->filter()->unique()->values();
        if ($skus->isEmpty()) return;
        $generics = PteSkuGenerico::whereIn('sku_base', $skus)->get()->keyBy('sku_base');
        $variants = PteSku::with('skuGenerico')->whereIn('sku', $skus)->get()->keyBy('sku');
        foreach ($items as $item) {
            $item->setRelation('personalizationGeneric', $generics->get($item->sku) ?? $variants->get($item->sku)?->skuGenerico);
        }
    }

    public static function assertSale(Cotizacion $quote): void
    {
        abort_unless(in_array($quote->estado?->nombre, ['Ventas', 'Aprobada por Cliente'], true),
            422, 'La cotización debe estar en Ventas para configurar personalización.');
    }

    public static function assertEditable(Cotizacion $quote): void
    {
        self::assertSale($quote);
        abort_if(in_array($quote->estado_personalizacion, ['sent_to_personalization', 'direct_delivery'], true)
            || Personalizacion::where('cotizacion_id', $quote->id)->exists(),
            409, 'Esta cotización ya fue enviada. No se modificó ni se volvió a enviar al taller.');
    }

    public static function validateConfig(DetalleCotizacion $item, array $groups, bool $complete): string
    {
        if (KitPersonalization::isKit($item)) {
            $plan = KitPersonalization::normalize($item, $groups, $complete);
            foreach (KitPersonalization::operationalItems($item, $plan) as $child) {
                if (self::validateConfig($child, $child->configuracion, $complete) !== 'none') return 'kit';
            }
            return 'none';
        }
        if ($complete && !$groups && $item->tipo_personalizacion !== 'none') {
            throw ValidationException::withMessages(['grupos' => ($item->producto ?: 'Producto') . ': falta configurar este producto.']);
        }
        if (self::informational($item)) {
            throw ValidationException::withMessages(['grupos' => 'Este ítem es informativo y solo se incluye en la cotización y su PDF.']);
        }
        Validator::make(['grupos' => $groups], [
            'grupos' => 'array|max:100',
            'grupos.*' => 'array',
            'grupos.*.nombre_grupo' => 'nullable|string|max:150',
            'grupos.*.archivo_adjunto' => 'nullable|string|max:2048',
            'grupos.*.tallas' => 'present|array|max:500',
            'grupos.*.tallas.*.talla' => 'required|string|max:50',
            'grupos.*.tallas.*.genero' => 'nullable|string|max:50',
            'grupos.*.tallas.*.color' => 'nullable|string|max:100',
            'grupos.*.tallas.*.cantidad' => 'required|integer|min:1|max:100000',
            'grupos.*.matrices' => 'sometimes|array|max:100',
            'grupos.*.matrices.*' => 'array',
            'grupos.*.matrices.*.tecnica' => 'nullable|in:Bordado,Estampado',
            'grupos.*.matrices.*.posicion' => 'nullable|string|max:100',
            'grupos.*.matrices.*.tipo_contenido' => 'nullable|in:logo,texto_fijo,texto_variable,fijo',
            'grupos.*.matrices.*.valor_fijo' => 'nullable|string|max:10000',
            'grupos.*.variables' => 'sometimes|array|max:10000',
            'grupos.*.variables.*.talla' => 'required|string|max:50',
            'grupos.*.variables.*.valor_variable' => 'nullable|string|max:500',
        ])->validate();

        $techniques = [];
        $total = 0;
        $fail = function (string $message) use ($item) {
            throw ValidationException::withMessages(['grupos' => ($item->producto ?: 'Producto') . ': ' . $message]);
        };
        foreach ($groups as $group) {
            $sizes = [];
            foreach ($group['tallas'] as $size) {
                $key = mb_strtoupper(trim($size['talla']));
                $sizes[$key] = ($sizes[$key] ?? 0) + (int) $size['cantidad'];
                $total += (int) $size['cantidad'];
            }
            $variableRequired = false;
            foreach ($group['matrices'] ?? [] as $matrix) {
                if (!empty($matrix['tecnica'])) $techniques[] = $matrix['tecnica'];
                $variableRequired = $variableRequired || ($matrix['tipo_contenido'] ?? '') === 'texto_variable';
                if ($complete) {
                    if (empty($matrix['tecnica']) || empty(trim($matrix['posicion'] ?? '')))
                        $fail('completa la técnica y posición de cada personalización.');
                    $content = $matrix['tipo_contenido'] ?? '';
                    if (!in_array($content, ['logo', 'texto_fijo', 'texto_variable'], true))
                        $fail('selecciona el tipo de contenido de cada personalización.');
                    if (in_array($content, ['logo', 'texto_fijo'], true) && trim($matrix['valor_fijo'] ?? '') === '')
                        $fail('falta el logo o texto fijo de una personalización.');
                    if (array_sum($sizes) === 0) $fail('cada grupo personalizado necesita prendas asignadas.');
                }
            }
            if ($complete && $variableRequired) {
                $names = $group['variables'] ?? [];
                // PDF/Word adjunto es una lista válida para revisión manual del taller.
                if (!$names && !empty($group['archivo_adjunto'])) continue;
                $counts = [];
                foreach ($names as $name) {
                    if (trim($name['valor_variable'] ?? '') === '') $fail('hay nombres sin completar.');
                    $key = mb_strtoupper(trim($name['talla']));
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                }
                ksort($sizes); ksort($counts);
                if ($sizes !== $counts) $fail('la cantidad de nombres por talla no coincide con las prendas del grupo.');
            }
        }
        $hasMatrices = collect($groups)->contains(fn ($g) => !empty($g['matrices']));
        if ($hasMatrices && self::generic($item)?->permite_personalizar === false) $fail('el producto no permite personalización.');
        if ($complete && $hasMatrices && $total !== (int) $item->cantidad) $fail('la distribución de tallas debe coincidir con la cantidad cotizada.');
        $techniques = array_unique($techniques);
        if (count($techniques) > 1 && in_array($item->tipo_personalizacion, ['print_then_embroidery', 'embroidery_then_print', 'bordado_luego_estampado', 'estampado_luego_bordado'], true)) {
            return $item->tipo_personalizacion;
        }
        return count($techniques) > 1 ? 'mixto' : (in_array('Estampado', $techniques, true) ? 'estampado' : ($techniques ? 'bordado' : 'none'));
    }
}
