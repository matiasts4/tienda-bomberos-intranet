<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PteTalla extends Model
{
    use HasFactory;
    protected $table = 'pte_tallas';
    public $timestamps = false;
    protected $guarded = [];
}