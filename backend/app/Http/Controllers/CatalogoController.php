<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

// MODELOS
use App\Models\PteUnidadNegocio;
use App\Models\PteOrigen;
use App\Models\PteGrpFamilia;
use App\Models\PteFamilia;
use App\Models\PteSubfamilia;
use App\Models\PteFamiliaTipo;
use App\Models\PteFamiliaFormato;
use App\Models\PteGenero;
use App\Models\PteColor;
use App\Models\PteTalla;

class CatalogoController extends Controller
{
    /**
     * TTL corto: los catálogos también se editan por SQL directo, que no dispara
     * eventos de modelo. Con 60 s un registro nuevo aparece casi de inmediato.
     */
    private const CACHE_TTL_SECONDS = 60;

    public const CACHE_KEYS = [
        'catalogos.unidades', 'catalogos.origenes', 'catalogos.grupos',
        'catalogos.familias', 'catalogos.subfamilias', 'catalogos.tipos',
        'catalogos.formatos', 'catalogos.generos', 'catalogos.colores',
        'catalogos.tallas', 'catalogos.cargos.v3',
    ];

    public static function forgetCache(): void
    {
        foreach (self::CACHE_KEYS as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Unidades de negocio
     */
    public function unidades()
    {
        return Cache::remember('catalogos.unidades', self::CACHE_TTL_SECONDS, function () {
            return PteUnidadNegocio::select('id', 'nombre')
                ->orderBy('nombre')
                ->get();
        });
    }

    /**
     * Orígenes
     */
    public function origenes()
    {
        return Cache::remember('catalogos.origenes', self::CACHE_TTL_SECONDS, function () {
            return PteOrigen::select('id', 'nombre')
                ->orderBy('nombre')
                ->get();
        });
    }

    /**
     * Grupos de familia (endpoint legacy)
     * Mantiene compatibilidad con rutas antiguas
     */
    public function gruposFamilia()
    {
        return $this->grupos();
    }

    /**
     * Grupos de familia
     */
    public function grupos()
    {
        return Cache::remember('catalogos.grupos', self::CACHE_TTL_SECONDS, function () {
            return PteGrpFamilia::select('id', 'nombre')
                ->orderBy('nombre')
                ->get();
        });
    }

    /**
     * Familias
     */
    public function familias()
    {
        return Cache::remember('catalogos.familias', self::CACHE_TTL_SECONDS, function () {
            return PteFamilia::select('id', 'nombre')
                ->orderBy('nombre')
                ->get();
        });
    }

    /**
     * Subfamilias
     */
    public function subfamilias()
    {
        return Cache::remember('catalogos.subfamilias', self::CACHE_TTL_SECONDS, function () {
            return PteSubfamilia::select('id', 'nombre', 'familia_id')
                ->orderBy('nombre')
                ->get();
        });
    }

    /**
     * Tipos de familia
     */
    public function tipos()
    {
        return Cache::remember('catalogos.tipos', self::CACHE_TTL_SECONDS, function () {
            return PteFamiliaTipo::select('id', 'nombre')
                ->orderBy('nombre')
                ->get();
        });
    }

    /**
     * Formatos de familia
     */
    public function formatos()
    {
        return Cache::remember('catalogos.formatos', self::CACHE_TTL_SECONDS, function () {
            return PteFamiliaFormato::select('id', 'nombre')
                ->orderBy('nombre')
                ->get();
        });
    }

    /**
     * Géneros
     */
    public function generos()
    {
        return Cache::remember('catalogos.generos', self::CACHE_TTL_SECONDS, function () {
            // Solo Hombre, Mujer y Unisex (los únicos géneros válidos para creación de productos)
            return PteGenero::select('id', 'nombre')
                ->whereIn('id', [0, 1, 2])
                ->orderBy('nombre')
                ->get();
        });
    }

    /**
     * Colores
     */
    public function colores()
    {
        return Cache::remember('catalogos.colores', self::CACHE_TTL_SECONDS, function () {
            return PteColor::select('id', 'nombre')
                ->orderBy('id')
                ->get();
        });
    }

    /**
     * Tallas
     */
    public function tallas()
    {
        return Cache::remember('catalogos.tallas', self::CACHE_TTL_SECONDS, function () {
            return PteTalla::select('id', 'nombre')
                ->orderBy('nombre')
                ->get();
        });
    }

    public function cargos()
    {
        // Cambiamos el nombre del key cache a v3 para refrescar
        return Cache::remember('catalogos.cargos.v3', self::CACHE_TTL_SECONDS, function () {
            return \App\Models\Cargo::select('id', 'nombre')
                ->orderBy('id')
                ->get()
                ->map(function ($cargo) {
                    $cargo->nombre = mb_strtoupper(trim($cargo->nombre), 'UTF-8');
                    return $cargo;
                });
        });
    }

    /**
     * Obtener TODOS los catálogos en una sola petición.
     * Optimiza la carga del frontend evitando 10 requests simultáneos.
     */
    public function index()
    {
        return response()->json([
            'unidades' => $this->unidades(),
            'origenes' => $this->origenes(),
            'grupos' => $this->grupos(),
            'familias' => $this->familias(),
            'subfamilias' => $this->subfamilias(),
            'tipos' => $this->tipos(),
            'formatos' => $this->formatos(),
            'generos' => $this->generos(),
            'colores' => $this->colores(),
            'tallas' => $this->tallas(),
            'cargos' => $this->cargos(),
        ]);
    }
}
