<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PteGenero extends Model
{
    use HasFactory;
    protected $table = 'pte_generos';
    public $timestamps = false;
    protected $guarded = [];
}