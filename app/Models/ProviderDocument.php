<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pièce justificative d'un prestataire (CNIB, RCCM, photo de l'atelier), vérifiée par l'admin.
 * Chaque type approuvé donne un badge de confiance sur la fiche publique.
 */
class ProviderDocument extends Model
{
    /** type => [libellé, badge public, colonne de date sur providers, aide] */
    public const KINDS = [
        'identity' => ['Pièce d\'identité (CNIB ou passeport)', '✓ Identité vérifiée', 'identity_verified_at', 'Photo lisible du recto de la CNIB du responsable.'],
        'business' => ['Registre du commerce (RCCM) ou IFU', '🏢 Entreprise vérifiée', 'business_verified_at', 'Document officiel au nom de l\'établissement.'],
        'electric' => ['Preuve de travail sur l\'électrique', '⚡ Spécialiste électrique', 'ev_verified_at', 'Photo d\'une réparation de moto ou voiture électrique dans votre atelier, ou attestation de formation.'],
        'workshop' => ['Photo de l\'atelier ou de la boutique', '📍 Adresse vérifiée', 'workshop_verified_at', 'Photo de la devanture avec l\'enseigne, prise sur place.'],
    ];

    public const STATUSES = [
        'pending' => ['En cours de vérification', 'bg-amber-100 text-amber-800'],
        'approved' => ['Vérifié', 'bg-emerald-100 text-emerald-800'],
        'rejected' => ['Refusé', 'bg-red-100 text-red-700'],
    ];

    protected $fillable = ['kind', 'path', 'number'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind][0] ?? $this->kind;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusClass(): string
    {
        return self::STATUSES[$this->status][1] ?? '';
    }

    /** Décision de l'admin : met à jour le badge correspondant de la fiche. */
    public function review(User $admin, bool $approved, ?string $reason = null): void
    {
        $this->forceFill([
            'status' => $approved ? 'approved' : 'rejected',
            'rejected_reason' => $approved ? null : $reason,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ])->save();

        $column = self::KINDS[$this->kind][2];
        $hasApproved = $approved || static::where('provider_id', $this->provider_id)->where('kind', $this->kind)->where('status', 'approved')->exists();
        $this->provider->forceFill([$column => $hasApproved ? ($this->provider->{$column} ?? now()) : null])->save();
    }
}
