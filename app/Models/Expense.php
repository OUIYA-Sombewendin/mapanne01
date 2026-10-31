<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = ['spent_at', 'category', 'amount', 'description', 'payment_method'];

    protected function casts(): array
    {
        return ['spent_at' => 'date', 'amount' => 'integer'];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
