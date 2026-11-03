<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MktUser extends Model
{
    use HasFactory;

    protected $table = 'mkt_users';

    protected $fillable = [
        'marketplace_id',
        'name',
        'rut',
        'email',
        'phone',
        'company_unit',
        'cargo',
        'password',
        'active',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Clean and normalize RUT (strip dots, dashes, spaces, uppercase).
     */
    public static function cleanRut(?string $rut): string
    {
        if (!$rut) return '';
        return strtoupper(preg_replace('/[^0-9kK]/', '', $rut));
    }

    /**
     * Format RUT to standard Chilean format XX.XXX.XXX-X
     */
    public static function formatRut(?string $rut): string
    {
        $clean = self::cleanRut($rut);
        if (strlen($clean) < 2) return $clean;
        
        $dv = substr($clean, -1);
        $num = substr($clean, 0, -1);
        return number_format((int)$num, 0, '', '.') . '-' . $dv;
    }

    /**
     * Relationship with Marketplace.
     */
    public function marketplace()
    {
        return $this->belongsTo(Marketplace::class);
    }
}
