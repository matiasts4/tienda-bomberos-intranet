<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PteFamiliaTipo extends Model
{
    use HasFactory;
    protected $table = 'pte_familia_tipos';
    public $timestamps = false;
    protected $guarded = [];
}