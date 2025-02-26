<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Card extends Model
{
    /** @use HasFactory<\Database\Factories\CardFactory> */
    use HasFactory;

    protected $fillable = [
        'title', 
        'description', 
        'status',
        'points',
        'status_updated_at',
    ];

    protected $casts = [
        'column_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(Column::class);
    }

    public function last_updated_at(): \Carbon\Carbon
    {
        $last_updated_at = $this->created_at;
        if ($this->status_updated_at) {
            $last_updated_at = $this->status_updated_at;
        }
        return $last_updated_at;
    }
}
