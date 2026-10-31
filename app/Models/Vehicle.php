<?php

namespace App\Models;

use App\Enums\VehicleType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehicle extends Model
{
    protected $fillable = ['type', 'brand', 'model', 'year', 'plate'];

    protected function casts(): array
    {
        return ['type' => VehicleType::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function title(): string
    {
        return trim($this->brand.' '.$this->model.($this->year ? ' ('.$this->year.')' : ''));
    }
}
