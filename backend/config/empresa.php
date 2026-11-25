<?php

/*
| Datos de la empresa que se imprimen en documentos (PDF de cotización).
| Los datos bancarios no se versionan: se configuran por ambiente en el .env.
| Si EMPRESA_PAGO_CUENTA está vacío, el PDF omite el bloque "Información de Pago".
*/

return [
    'pago' => [
        'banco' => env('EMPRESA_PAGO_BANCO'),
        'cuenta' => env('EMPRESA_PAGO_CUENTA'),
        'email' => env('EMPRESA_PAGO_EMAIL'),
    ],
];
