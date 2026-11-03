<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PteColor extends Model
{
    use HasFactory;
    protected $table = 'pte_colores';
    public $timestamps = false;
    protected $guarded = [];
}