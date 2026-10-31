<?php

namespace App\Models;

use App\Models\Concerns\Lockable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use Lockable;

    protected $fillable = ['rating', 'comment'];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_hidden' => 'boolean',
            'is_pending' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // La note moyenne du prestataire suit toujours ses avis.
        static::saved(fn (Review $review) => $review->provider->refreshRating());
        static::deleted(fn (Review $review) => $review->provider->refreshRating());
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
