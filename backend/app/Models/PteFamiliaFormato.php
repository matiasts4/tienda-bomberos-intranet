<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PteFamiliaFormato extends Model
{
    use HasFactory;
    protected $table = 'pte_familia_formatos';
    public $timestamps = false;
    protected $guarded = [];
}