<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Group extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'description'];

    private $rules = [
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
    ];

    /**
     * Get the users that are members of the group.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function users(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'group_user');
}


    /**
     * The boards that belong to the Group
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function boards(): HasMany
    {
        return $this->hasMany(Board::class);
    }
}
