<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PteFamilia extends Model
{
    use HasFactory;
    protected $table = 'pte_familias';
    public $timestamps = false;
    protected $guarded = [];
}