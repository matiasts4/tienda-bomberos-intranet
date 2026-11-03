<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CotizacionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'oportunidad_id' => ['nullable', 'integer', 'exists:oportunidades,id'],
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'ejecutivo_id' => ['required', 'integer', 'exists:ejecutivos,id'],
            'estado_id' => ['required', 'integer', 'exists:estados,id'],

            // Campos cabecera 
            'numero' => ['nullable', 'string', 'max:32', 'unique:cotizaciones,numero'],
            'fecha_creacion' => ['nullable', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string'],

            'total_neto' => ['nullable', 'integer', 'min:0'],
            'iva' => ['nullable', 'integer', 'min:0'],
            'total' => ['nullable', 'integer', 'min:0'],

            'plazo_produccion' => ['nullable', 'string', 'max:120'],
            'condiciones_pago' => ['nullable', 'string', 'max:120'],
            'despacho' => ['nullable', 'string', 'max:120'],
            'origen' => ['nullable', 'string', 'in:manual,oportunidad,excel'],

            // Detalle
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.sku' => ['required', 'string', 'max:50'],
            'detalles.*.n_item' => ['nullable', 'integer', 'min:1'],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1'],
            'detalles.*.subtotal' => ['nullable', 'integer', 'min:0'],
            'detalles.*.is_personalizable' => ['nullable', 'boolean'],

            // Campos detalle 
            'detalles.*.producto' => ['nullable', 'string', 'max:120'],
            'detalles.*.talla' => ['nullable', 'string', 'max:30'],
            'detalles.*.color' => ['nullable', 'string', 'max:60'],
            'detalles.*.genero' => ['nullable', 'string', 'max:30'],
            'detalles.*.tipo_personalizacion' => ['nullable', 'string', 'max:60'],
            'detalles.*.precio_unitario' => ['nullable', 'integer', 'min:0'],
            'detalles.*.total_neto' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
