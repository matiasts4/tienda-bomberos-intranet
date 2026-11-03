<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CotizacionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza valores antes de validar:
     * - Convierte "12.597" => 12597 (para precio_unitario, total_neto, subtotal, total, etc.)
     * - Normaliza strings vacíos a null en detalles
     */
    protected function prepareForValidation(): void
    {
        $input = $this->all();

        // Helper: "12.597" / "$12.597" / "12,597" => 12597
        $toInt = function ($v) {
            if ($v === null)
                return null;
            if (is_int($v))
                return $v;
            if (is_float($v))
                return (int) round($v);

            if (is_string($v)) {
                $s = trim($v);
                if ($s === '')
                    return null;

                // deja solo dígitos
                $digits = preg_replace('/\D+/', '', $s);
                if ($digits === '')
                    return null;

                return (int) $digits;
            }

            return $v;
        };

        // Igual que $toInt pero sin redondear los floats: conserva los decimales
        // exactos de cada línea (p. ej. precio con descuento calculado en el
        // frontend) para que el total de cabecera se pueda sumar ANTES de
        // redondear, en vez de sumar valores ya redondeados por línea.
        $toExact = function ($v) {
            if ($v === null)
                return null;
            if (is_int($v) || is_float($v))
                return (float) $v;

            if (is_string($v)) {
                $s = trim($v);
                if ($s === '')
                    return null;

                $digits = preg_replace('/\D+/', '', $s);
                if ($digits === '')
                    return null;

                return (float) $digits;
            }

            return null;
        };

        // Normaliza totales de cabecera si vienen como string
        foreach (['total_neto', 'iva', 'total'] as $k) {
            if (array_key_exists($k, $input)) {
                $input[$k] = $toInt($input[$k]);
            }
        }

        // Normaliza detalles
        if (isset($input['detalles']) && is_array($input['detalles'])) {
            $input['detalles'] = array_values(array_map(function ($d) use ($toInt, $toExact) {
                if (!is_array($d))
                    return $d;

                // strings vacíos -> null (y trim)
                foreach (['sku', 'producto', 'talla', 'color', 'genero', 'tipo_personalizacion'] as $k) {
                    if (array_key_exists($k, $d) && is_string($d[$k])) {
                        $d[$k] = trim($d[$k]);
                        $d[$k] = $d[$k] === '' ? null : $d[$k];
                    }
                }

                // Captura el neto exacto de la línea (sin redondear) ANTES de
                // convertir precio_unitario/total_neto a entero, para poder
                // agregar el total de cabecera redondeando una sola vez.
                $totalNetoExacto = $toExact($d['total_neto'] ?? null);
                if ($totalNetoExacto === null) {
                    $precioExacto = $toExact($d['precio_unitario'] ?? null);
                    $cantidadExacta = is_numeric($d['cantidad'] ?? null) ? (float) $d['cantidad'] : 0.0;
                    $totalNetoExacto = $precioExacto !== null ? $precioExacto * $cantidadExacta : 0.0;
                }
                $d['_total_neto_exacto'] = $totalNetoExacto;

                // numéricos con puntos -> int
                foreach (['cantidad', 'subtotal', 'precio_unitario', 'total_neto', 'n_item'] as $k) {
                    if (array_key_exists($k, $d)) {
                        $d[$k] = $toInt($d[$k]);
                    }
                }

                // boolean
                if (array_key_exists('is_personalizable', $d)) {
                    $d['is_personalizable'] = filter_var(
                        $d['is_personalizable'],
                        FILTER_VALIDATE_BOOLEAN,
                        FILTER_NULL_ON_FAILURE
                    );
                }

                return $d;
            }, $input['detalles']));
        }

        $this->replace($input);
    }

    /**
     * Regla clave:
     * - Si envías detalles, cada ítem puede venir:
     *   - con sku (cuando existe en catálogo), o
     *   - sin sku, PERO con "producto" (cotización a nivel producto base)
     *
     * Esto permite cotizar sin talla/color (y sin depender de SKU variante).
     */
    public function rules(): array
    {
        return [
            'oportunidad_id' => ['sometimes', 'nullable', 'integer', 'exists:oportunidades,id'],
            'cliente_id' => ['sometimes', 'nullable', 'string', 'max:15', 'exists:clientes,id'],
            'contacto_id' => ['sometimes', 'nullable', 'integer', 'exists:contactos,id'],
            'cuerpo_id' => ['sometimes', 'nullable', 'string', 'max:15', 'exists:cuerpos,id'],
            'compania_id' => ['sometimes', 'nullable', 'string', 'max:15', 'exists:companias,id'],
            'ejecutivo_id' => ['sometimes', 'integer', 'exists:ejecutivos,id'],
            'estado_id' => ['sometimes', 'integer', 'exists:estados,id'],

            'numero' => ['sometimes', 'nullable', 'string', 'max:32'],
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'fecha_creacion' => ['sometimes', 'nullable', 'date'],
            'fecha_vencimiento' => ['sometimes', 'nullable', 'date'],
            'observaciones' => ['sometimes', 'nullable', 'string'],

            'total_neto' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'iva' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'total' => ['sometimes', 'nullable', 'integer', 'min:0'],

            'plazo_produccion' => ['sometimes', 'nullable', 'string', 'max:120'],
            'condiciones_pago' => ['sometimes', 'nullable', 'string', 'max:120'],
            'despacho' => ['sometimes', 'nullable', 'string', 'max:120'],

            'origen' => ['sometimes', 'nullable', 'string', 'in:manual,oportunidad,excel,clientes'],

            // Si mandas detalles, reemplazamos el set completo
            'detalles' => ['sometimes', 'array', 'min:1'],

            // ✅ SKU AHORA ES OPCIONAL (para permitir cotizar producto sin variante)
            // OJO: NO ponemos exists acá porque si no existe te bloquea igual.
            // La existencia (si quieres) se valida en Controller o con una regla custom.
            'detalles.*.sku' => ['nullable', 'string', 'max:50'],

            'detalles.*.n_item' => ['nullable', 'integer', 'min:1'],
            'detalles.*.cantidad' => ['required_with:detalles', 'integer', 'min:1'],
            'detalles.*.subtotal' => ['nullable', 'integer', 'min:0'],
            'detalles.*.is_personalizable' => ['nullable', 'boolean'],

            // ✅ Producto base para cuando NO hay SKU
            'detalles.*.producto' => ['nullable', 'string', 'max:120'],

            // Estos pasan a ser totalmente opcionales (no te frenan)
            'detalles.*.talla' => ['nullable', 'string', 'max:30'],
            'detalles.*.color' => ['nullable', 'string', 'max:60'],
            'detalles.*.genero' => ['nullable', 'string', 'max:30'],
            'detalles.*.tipo_personalizacion' => ['nullable', 'string', 'max:60'],
            'detalles.*.precio_unitario' => ['nullable', 'integer', 'min:0'],
            'detalles.*.total_neto' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * ✅ Validación adicional:
     * Si un detalle NO trae sku, entonces DEBE traer producto.
     * (Así evitas “ítems vacíos”).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $detalles = $this->input('detalles');

            if (!is_array($detalles))
                return;

            foreach ($detalles as $i => $d) {
                if (!is_array($d))
                    continue;

                $sku = $d['sku'] ?? null;
                $producto = $d['producto'] ?? null;

                $hasSku = is_string($sku) && trim($sku) !== '';
                $hasProducto = is_string($producto) && trim($producto) !== '';

                if (!$hasSku && !$hasProducto) {
                    $v->errors()->add("detalles.$i.sku", "Debes enviar 'sku' o 'producto' en cada detalle.");
                    $v->errors()->add("detalles.$i.producto", "Debes enviar 'sku' o 'producto' en cada detalle.");
                }
            }
        });
    }
}
