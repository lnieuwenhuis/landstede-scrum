<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'categories';
    protected $fillable = ['name', 'description', 'color'];

    public function cards()
    {
        return $this->hasMany(Card::class);
    }
}
