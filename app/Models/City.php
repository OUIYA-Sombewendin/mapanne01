<?php

namespace App\Models;

use App\Models\Concerns\Lockable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use Lockable;

    protected $fillable = ['name', 'slug', 'latitude', 'longitude', 'is_active'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function neighborhoods(): HasMany
    {
        return $this->hasMany(Neighborhood::class)->orderBy('name');
    }
}
