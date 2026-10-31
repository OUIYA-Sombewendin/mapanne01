<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartCategory extends Model
{
    protected $fillable = ['name', 'slug', 'icon', 'position'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
