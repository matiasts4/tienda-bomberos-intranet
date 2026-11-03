<?php

// Ejecutar la misma migración que producción, también sobre el esquema reducido de pruebas.
return require __DIR__ . '/../../../database/migrations/2026_08_31_034903_add_es_informativo_cotizacion_to_pte_sku_genericos_table.php';
