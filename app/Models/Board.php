<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Board extends Model
{
    protected $fillable = ['title', 'description'];

    private $rules = [
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
    ];
}
