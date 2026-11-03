<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PteUnidadNegocio extends Model
{
    use HasFactory;
    protected $table = 'pte_unidades_negocio';
    public $timestamps = false;
    protected $guarded = [];
}