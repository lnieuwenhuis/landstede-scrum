<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = ['title', 'description'];

    private $rules = [
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
