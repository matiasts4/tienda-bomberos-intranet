<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PteSubfamilia extends Model
{
    use HasFactory;
    protected $table = 'pte_subfamilias';
    public $timestamps = false;
    protected $guarded = [];
}   