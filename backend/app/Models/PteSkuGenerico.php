<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PteSkuGenerico extends Model
{
    protected $table = 'pte_sku_genericos';
    protected $guarded = [];
    protected $casts = [
        'es_informativo_cotizacion' => 'boolean',
        'permite_personalizar' => 'boolean',
        'permite_cotizar' => 'boolean',
        'modo_kit' => 'boolean',
        'componentes_kit' => 'array',
        'activo' => 'boolean',
        'archived_at' => 'datetime',
        'estado_catalogo' => 'string',
    ];

    protected static function booted()
    {
        static::saving(function ($generic) {
            if ($generic->isDirty('activo') && !$generic->isDirty('estado_catalogo')) {
                if (!$generic->activo) {
                    if ($generic->getOriginal('estado_catalogo') === 'publicado') {
                        $generic->estado_catalogo = 'archivado';
                        if (!$generic->archived_at) {
                            $generic->archived_at = now();
                        }
                    }
                } elseif ($generic->activo && $generic->estado_catalogo !== 'publicado') {
                    $generic->estado_catalogo = 'publicado';
                    $generic->archived_at = null;
                }
            }

            if ($generic->isDirty('estado_catalogo') && !$generic->isDirty('activo')) {
                $generic->activo = ($generic->estado_catalogo === 'publicado');
                if ($generic->estado_catalogo === 'archivado' && !$generic->archived_at) {
                    $generic->archived_at = now();
                } elseif ($generic->estado_catalogo !== 'archivado') {
                    $generic->archived_at = null;
                    $generic->archived_by = null;
                    $generic->archive_reason = null;
                }
            }
        });
    }

    // ── RELACIONES DE CATÁLOGO (propiedad del producto base) ──

    public function unidadNegocio()
    {
        return $this->belongsTo(PteUnidadNegocio::class, 'unidad_negocio_id');
    }

    public function origen()
    {
        return $this->belongsTo(PteOrigen::class, 'origen_id');
    }

    public function grpFamilia()
    {
        return $this->belongsTo(PteGrpFamilia::class, 'grp_familia_id');
    }

    public function familia()
    {
        return $this->belongsTo(PteFamilia::class, 'familia_id');
    }

    public function subfamilia()
    {
        return $this->belongsTo(PteSubfamilia::class, 'subfamilia_id');
    }

    public function familiaTipo()
    {
        return $this->belongsTo(PteFamiliaTipo::class, 'familia_tipo_id');
    }

    public function familiaFormato()
    {
        return $this->belongsTo(PteFamiliaFormato::class, 'familia_formato_id');
    }

    public function genero()
    {
        return $this->belongsTo(PteGenero::class, 'genero_id');
    }

    public function archivedBy()
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    // ── RELACIÓN CON VARIANTES (FK formal) ──

    /**
     * Obtener todos los SKU variantes (pte_skus) que pertenecen a este producto genérico.
     * Vinculado por FK formal sku_generico_id.
     */
    public function variantes()
    {
        return $this->hasMany(PteSku::class, 'sku_generico_id');
    }

    public function variantesActivas()
    {
        return $this->hasMany(PteSku::class, 'sku_generico_id')->where('activo', true);
    }

    // ── SCOPES DE CICLO DE VIDA ──

    public function scopePublicados($query)
    {
        return $query->where('estado_catalogo', 'publicado');
    }

    public function scopeBorradores($query)
    {
        return $query->where('estado_catalogo', 'borrador');
    }

    public function scopeArchivados($query)
    {
        return $query->where('estado_catalogo', 'archivado');
    }
}
