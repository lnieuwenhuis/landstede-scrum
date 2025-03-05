<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Board extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 
        'description'
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'board_user');
    }

    public function columns(): HasMany
    {
        return $this->hasMany(Column::class);
    }

    public function doneColumns(): HasMany
    {
        return $this->hasMany(Column::class)->where('is_done_column', true);
    }

    public function addUser(User $user)
    {
        $this->users()->attach($user);
    }

    public function removeUser(User $user)
    {
        $this->users()->detach($user);
    }

    public function getCreator(): User
    {
        return User::find($this->creator_id);
    }
}
