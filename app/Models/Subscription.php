<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Abonnement d'un prestataire. Le paiement se fait hors plateforme (Orange Money, Moov Money, espèces) :
 * le prestataire demande une offre, envoie la preuve de paiement, l'admin vérifie puis active ou refuse.
 */
class Subscription extends Model
{
    public const STATUSES = [
        'pending' => ['En attente de paiement', 'bg-amber-100 text-amber-800'],
        'active' => ['Actif', 'bg-emerald-100 text-emerald-800'],
        'expired' => ['Expiré', 'bg-ink-100 text-ink-600'],
        'cancelled' => ['Annulé', 'bg-red-100 text-red-700'],
        'rejected' => ['Paiement refusé', 'bg-red-100 text-red-700'],
    ];

    /** Paiement mobile hors plateforme : opérateurs proposés (numéros dans Réglages › Abonnements). */
    public const OPERATORS = ['orange' => 'Orange Money', 'moov' => 'Moov Money'];

    /** Statuts où une référence de transaction est « utilisée ». */
    public const USED_STATUSES = ['pending', 'active', 'expired'];

    protected $fillable = [
        'plan_id', 'status', 'starts_at', 'ends_at', 'amount', 'payment_method', 'payment_reference', 'notes',
        'payment_operator', 'payer_phone', 'proof_path', 'proof_submitted_at', 'rejected_reason', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'amount' => 'integer',
            'proof_submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    /** Abonnements en cours (actifs et non échus). */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusClass(): string
    {
        return self::STATUSES[$this->status][1] ?? '';
    }

    /** Opérateurs dont l'admin a renseigné le numéro de réception. */
    public static function operators(): array
    {
        return array_filter(self::OPERATORS, fn ($label, $key) => filled(setting("payment_{$key}_number")), ARRAY_FILTER_USE_BOTH);
    }

    public function operatorLabel(): ?string
    {
        return self::OPERATORS[$this->payment_operator] ?? null;
    }

    public function hasProof(): bool
    {
        return $this->proof_submitted_at !== null;
    }

    /** Référence déjà utilisée par un autre abonnement (paiement réutilisé ?). */
    public static function referenceUsed(string $operator, string $reference, ?int $except = null): bool
    {
        return static::where('payment_operator', $operator)
            ->whereRaw('upper(payment_reference) = ?', [mb_strtoupper(trim($reference))])
            ->whereIn('status', self::USED_STATUSES)
            ->when($except, fn ($q) => $q->whereKeyNot($except))
            ->exists();
    }

    public function daysLeft(): ?int
    {
        return $this->ends_at ? max(0, (int) ceil(now()->diffInDays($this->ends_at, false))) : null;
    }

    /** Active l'abonnement ; l'abonnement actif précédent du prestataire est clos. */
    public function activate(?User $by = null, ?DateTimeInterface $start = null, ?int $days = null): void
    {
        $start = $start ? Carbon::instance($start) : now();
        $days ??= $this->plan->period_days;

        static::where('provider_id', $this->provider_id)->where('id', '!=', $this->id)->where('status', 'active')
            ->update(['status' => 'expired', 'ends_at' => now()]);

        $this->forceFill([
            'status' => 'active',
            'starts_at' => $start,
            'ends_at' => $days > 0 ? $start->copy()->addDays($days) : null,
            'activated_by' => $by?->id,
            'reviewed_at' => $by ? now() : $this->reviewed_at,
        ])->save();

        $this->provider->syncPlanBenefits();
    }
}
