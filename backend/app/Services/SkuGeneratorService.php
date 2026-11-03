<?php

namespace App\Services;

use App\Exceptions\ProductCatalogConflictException;
use Illuminate\Validation\ValidationException;

class SkuGeneratorService
{
    /**
     * Valida los rangos numéricos de cada segmento del SKU.
     *
     * Esquema de 15 dígitos canónicos:
     * - Unidad de negocio: 1 dígito (0 - 9)
     * - Origen: 1 dígito (0 - 9)
     * - Grupo familia: 1 dígito (0 - 9)
     * - Familia: 1 dígito (0 - 9)
     * - Subfamilia: 2 dígitos (0 - 99)
     * - Familia tipo: 2 dígitos (0 - 99)
     * - Familia formato: 2 dígitos (0 - 99)
     * - Género: 1 dígito (0 - 9)
     * - Color: 2 dígitos (0 - 99)
     * - Talla: 2 dígitos (0 - 99)
     */
    public function validateSegmentLimits(array $attributes): void
    {
        $limits = [
            'unidad_negocio_id' => [0, 9, 'Unidad de Negocio'],
            'origen_id' => [0, 9, 'Origen'],
            'grp_familia_id' => [0, 9, 'Grupo de Familia'],
            'familia_id' => [0, 9, 'Familia'],
            'subfamilia_id' => [0, 99, 'Subfamilia'],
            'familia_tipo_id' => [0, 99, 'Tipo de Familia'],
            'familia_formato_id' => [0, 99, 'Formato de Familia'],
            'genero_id' => [0, 9, 'Género'],
        ];

        $errors = [];
        foreach ($limits as $key => [$min, $max, $label]) {
            if (array_key_exists($key, $attributes)) {
                $val = (int) $attributes[$key];
                if ($val < $min || $val > $max) {
                    $errors[$key] = ["El segmento '{$label}' debe estar entre {$min} y {$max}."];
                }
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Valida rangos para atributos de variante (color y talla).
     */
    public function validateVariantSegmentLimits(int $colorId, int $tallaId): void
    {
        $errors = [];
        if ($colorId < 0 || $colorId > 99) {
            $errors['color_id'] = ['El ID de color debe estar entre 0 y 99.'];
        }
        if ($tallaId < 0 || $tallaId > 99) {
            $errors['talla_id'] = ['El ID de talla debe estar entre 0 y 99.'];
        }
        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Genera el SKU base canónico de 15 dígitos (11 dígitos de base + '0000').
     */
    public function generateBaseSku(
        int $unidadId,
        int $origenId,
        int $grupoId,
        int $familiaId,
        int $subfamiliaId,
        int $tipoId,
        int $formatoId,
        int $generoId
    ): string {
        $this->validateSegmentLimits([
            'unidad_negocio_id' => $unidadId,
            'origen_id' => $origenId,
            'grp_familia_id' => $grupoId,
            'familia_id' => $familiaId,
            'subfamilia_id' => $subfamiliaId,
            'familia_tipo_id' => $tipoId,
            'familia_formato_id' => $formatoId,
            'genero_id' => $generoId,
        ]);

        $prefix = sprintf(
            '%01d%01d%01d%01d%02d%02d%02d%01d',
            $unidadId,
            $origenId,
            $grupoId,
            $familiaId,
            $subfamiliaId,
            $tipoId,
            $formatoId,
            $generoId
        );

        return $prefix.'0000';
    }

    /**
     * Genera el SKU de variante canónica a partir del SKU base del producto.
     */
    public function generateVariantSku(string $skuBase, int $colorId, int $tallaId): string
    {
        if (! $this->isCanonicalBaseSku($skuBase)) {
            throw new ProductCatalogConflictException(
                "El SKU base '{$skuBase}' no tiene el formato canónico de 15 dígitos para generar variantes."
            );
        }

        $this->validateVariantSegmentLimits($colorId, $tallaId);

        $prefix = substr($skuBase, 0, 11);

        return $prefix.sprintf('%02d%02d', $colorId, $tallaId);
    }

    /**
     * Comprueba si un SKU cumple con el estándar canónico de 15 dígitos numéricos.
     */
    public function isCanonicalSku(string $sku): bool
    {
        return preg_match('/^\d{15}$/', $sku) === 1;
    }

    /**
     * Comprueba si un SKU base cumple con el estándar canónico (11 dígitos numéricos + 0000).
     */
    public function isCanonicalBaseSku(string $skuBase): bool
    {
        return preg_match('/^\d{11}0000$/', $skuBase) === 1;
    }

    /**
     * Extrae el prefijo de 11 dígitos si el SKU es canónico.
     */
    public function extractPrefix(string $sku): ?string
    {
        if ($this->isCanonicalSku($sku)) {
            return substr($sku, 0, 11);
        }

        return null;
    }
}
