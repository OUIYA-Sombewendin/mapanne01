<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    public const DAYS = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];

    public $timestamps = false;

    protected $fillable = ['day', 'opens_at', 'closes_at'];

    protected function casts(): array
    {
        return ['day' => 'integer'];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function dayName(): string
    {
        return self::DAYS[$this->day];
    }

    public function label(): string
    {
        if ($this->opens_at === $this->closes_at) {
            return '24h/24';
        }

        return substr($this->opens_at, 0, 5).' – '.substr($this->closes_at, 0, 5);
    }
}
