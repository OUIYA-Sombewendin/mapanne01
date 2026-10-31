<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Facture ou devis émis par un prestataire depuis son espace de gestion. */
class Invoice extends Model
{
    public const STATUSES = [
        'unpaid' => ['Non payée', 'bg-red-100 text-red-700'],
        'partial' => ['Payée en partie', 'bg-amber-100 text-amber-800'],
        'paid' => ['Payée', 'bg-emerald-100 text-emerald-800'],
        'draft' => ['Devis envoyé', 'bg-sky-100 text-sky-800'],
        'accepted' => ['Devis accepté', 'bg-emerald-100 text-emerald-800'],
        'cancelled' => ['Annulée', 'bg-ink-100 text-ink-500'],
    ];

    protected $fillable = ['kind', 'number', 'status', 'customer_name', 'customer_phone', 'issued_at', 'subtotal', 'discount', 'total', 'amount_paid', 'payment_method', 'notes'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'subtotal' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
            'amount_paid' => 'integer',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /** Qui a fait la vente (propriétaire ou employé). */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function convertedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'converted_from_id');
    }

    /** Factures qui comptent dans le chiffre d'affaires. */
    public function scopeSales(Builder $query): void
    {
        $query->where('kind', 'invoice')->where('status', '!=', 'cancelled');
    }

    public function isQuote(): bool
    {
        return $this->kind === 'quote';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function balance(): int
    {
        return $this->isCancelled() ? 0 : max(0, $this->total - $this->amount_paid);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusClass(): string
    {
        return self::STATUSES[$this->status][1] ?? '';
    }

    public static function paymentStatus(int $total, int $paid): string
    {
        return $paid <= 0 ? 'unpaid' : ($paid >= $total ? 'paid' : 'partial');
    }

    public function title(): string
    {
        return ($this->isQuote() ? 'Devis' : 'Facture').' '.$this->number;
    }
}
