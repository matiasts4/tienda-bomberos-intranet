<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PteColor;
use App\Models\PteTalla;
use App\Models\PteGenero;

class CotizacionOptionsController extends Controller
{
    public function index()
    {
        // 1. DB Data
        $colores = PteColor::where('is_activo', true)->pluck('nombre');
        $tallas = PteTalla::where('is_activo', true)->orderBy('orden')->pluck('nombre');
        $generos = PteGenero::where('is_activo', true)
            ->pluck('nombre');

        // 2. CSV Data (Temporary / Extracted from CSV)
        // Hardcoded to avoid file dependency
        $productos = [
            "Banano Institucional",
            "Bandera",
            "Blusa Oxford",
            "Camisa Oxford",
            "Cargo",
            "Chaqueta Antiacido",
            "Chaqueta Pluma",
            "Cortaviento",
            "Frase",
            "Gorro Safari",
            "Gorro de Lana",
            "Herramiuenta Multiproposito Alicate",
            "Herramiuenta Multiproposito Hacha",
            "Herramiuenta Multiproposito Martillo",
            "KIT CUARTEL",
            "Kit Cuartel - 3 prendas",
            "Kit Cuartel full-5 prendas",
            "Logo",
            "Martillo Emergencia",
            "Mochila Institucional",
            "Mochila Notebook",
            "Nombre",
            "Pantalon Antiacido",
            "Pantalon Gersa",
            "Pantalon Hazmat",
            "Pantalon Multirol",
            "Pantalon Tactico",
            "Pantalon Usar",
            "Parche 10 x 10",
            "Parche 10 x 25",
            "Parche 2,5 x 10",
            "Parche 5 x 10",
            "Parka 3 en 1",
            "Parka Impermeable",
            "Polar Clasico",
            "Polar Tactico",
            "Polera Casual MC",
            "Polera Casual ML",
            "Polera Pique MC",
            "Polera Pique ML",
            "Polera Polo MC",
            "Polera Polo ML",
            "Polera Urbana MC",
            "Polera Urbana ML",
            "Poleron Capucha",
            "Poleron Cuartel",
            "Poleron Polo",
            "Quepi",
            "Quepi Capitan",
            "Quepi Comandante",
            "Quepi Director",
            "Quepi Secretaria",
            "Quepi Secretario",
            "Quepi Teniente",
            "Quepi Tesorera",
            "Quepi Tesorero",
            "Quepi Vice superintendente",
            "Quepi superintendente",
            "Reversible",
            "Softshell",
            "Traje Antiacido",
            "Traje Gersa",
            "Traje Hazmat",
            "Traje Multirol",
            "Traje Usar",
            "Traje de Brigada"
        ];

        $personalizaciones = [
            "Bordado",
            "Estampado",
            "Bordado y Estampado",
            "Sin Personalización",
            "Generico"
        ];

        $plazos = [
            "20 dias",
            "15 dias habiles",
            "20 dias habiles",
            "30 dias habiles",
            "7 dias habiles",
            "Stock Disponible"
        ];

        $condiciones_pago = [
            "Contado - Transferencia",
            "Credito 15/30 dias",
            "Credito_Anticipo 50%/saldo 50% entrega"
        ];

        $despacho = [
            "Incluido",
            "Por pagar"
        ];

        return response()->json([
            'colores' => $colores,
            'tallas' => $tallas,
            'generos' => $generos,
            'productos' => $productos,
            // 'productos' => array_values(array_unique($productos)), // Already unique in hardcoded list
            'personalizaciones' => $personalizaciones,
            'plazos_produccion' => $plazos,
            'condiciones_pago' => $condiciones_pago,
            'despacho' => $despacho,
        ]);
    }
}
