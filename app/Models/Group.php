<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\User;
use App\Models\Board;

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
    public function board(): HasOne
    {
        return $this->hasOne(Board::class);
    }

    /**
     * Add a user to the group
     * 
     * @param User $user
     * @return void
     */
    public function addUser(User $user)
    {
        $this->users()->attach($user);
    }

    public function removeUser(User $user)
    {
        $this->users()->detach($user);
    }
}
