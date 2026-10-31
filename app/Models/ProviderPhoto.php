<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProviderPhoto extends Model
{
    protected $fillable = ['path'];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
