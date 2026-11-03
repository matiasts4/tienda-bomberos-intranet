<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FactoryLog extends Model
{
    use HasFactory;

    protected $table = 'logs_fabrica';

    protected $fillable = [
        'action',
        'description',
        'details',
        'entity_type',
        'entity_id',
        'user_id',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
