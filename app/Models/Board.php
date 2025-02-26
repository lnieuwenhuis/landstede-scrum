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

    protected $casts = [
        'group_id' => 'integer',
    ];

    public function group(): BelongsTo 
    {
        return $this->belongsTo(Group::class);
    }

    public function columns(): HasMany
    {
        return $this->hasMany(Column::class);
    }

    public function doneColumns(): HasMany
    {
        return $this->hasMany(Column::class)->where('is_done_column', true);
    }
}
