<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartRequestOffer extends Model
{
    protected $fillable = ['product_id', 'price', 'condition', 'message'];

    protected function casts(): array
    {
        return ['price' => 'integer'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PartRequest::class, 'part_request_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function priceLabel(): string
    {
        return Product::formatPrice($this->price);
    }
}
