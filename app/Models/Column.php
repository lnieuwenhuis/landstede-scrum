<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Column extends Model
{
    /** @use HasFactory<\Database\Factories\ColumnFactory> */
    use HasFactory;

    protected $fillable = ['title'];

    protected $casts = [
        'board_id' => 'integer',
        'is_done_column' => 'boolean',
    ];

    public static function boot()
    {
        parent::boot();

        static::deleting(function ($column) {
            $column->cards()->delete();
        });
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }
}
