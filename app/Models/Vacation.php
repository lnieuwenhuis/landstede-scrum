<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vacation extends Model
{
    /** @use HasFactory<\Database\Factories\VacationFactory> */
    use HasFactory;

    protected $fillable = [
        'schoolyear',
        'json',
        'status'
    ];

    public function currentYear()
    {
        return $this->where('schoolyear', date('Y'))->first();
    }

    public static function activeVacation()
    {
        return self::where('status', 'active')->first();
    }
}
