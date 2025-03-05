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
        'json'
    ];

    public function currentYear()
    {
        return $this->where('schoolyear', date('Y'))->first();
    }
}
