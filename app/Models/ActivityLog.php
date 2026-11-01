<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'details'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public static function record(string $action, ?Model $subject = null, array $details = []): self
    {
        return static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'details' => $details ?: null,
        ]);
    }

    /** Libellés lisibles de l'historique (les actions inconnues s'affichent telles quelles). */
    public const LABELS = [
        'auth.two_factor_failed' => 'Code de connexion admin refusé',
        'auth.admin_login' => 'Connexion à l\'administration',
        'backup.created' => 'Sauvegarde créée', 'backup.deleted' => 'Sauvegarde supprimée',
        'backup.downloaded' => 'Sauvegarde téléchargée', 'backup.restored' => 'Sauvegarde restaurée',
        'data.exported' => 'Export de données', 'data.imported' => 'Import de données',
        'page.created' => 'Page créée', 'page.updated' => 'Page modifiée',
        'part_request.closed_by_admin' => 'Demande de pièce clôturée',
        'plan.created' => 'Offre créée', 'plan.updated' => 'Offre modifiée', 'plan.default' => 'Offre par défaut changée',
        'product.approved' => 'Produit publié', 'product.hidden' => 'Produit masqué', 'product.restored' => 'Produit republié',
        'provider.approved' => 'Prestataire validé', 'provider.rejected' => 'Prestataire refusé',
        'provider.suspended' => 'Prestataire suspendu', 'provider.reactivated' => 'Prestataire réactivé',
        'provider.edited_by_admin' => 'Fiche modifiée par l\'admin', 'provider.changes_reviewed' => 'Changements vérifiés',
        'provider.sensitive_changes' => 'Changement sensible d\'une fiche', 'provider.resubmitted' => 'Fiche renvoyée en vérification',
        'provider.featured' => 'Mis à la une', 'provider.unfeatured' => 'Retiré de la une',
        'provider_type.created' => 'Type de prestataire créé', 'provider_type.updated' => 'Type de prestataire modifié', 'assist.closed' => 'Demande de dépannage fermée', 'provider_type.modules' => 'Modules d\'un métier modifiés',
        'review.approved' => 'Avis publié', 'review.hidden' => 'Avis masqué', 'review.restored' => 'Avis rétabli',
        'role.created' => 'Rôle créé', 'role.updated' => 'Rôle modifié', 'role.deleted' => 'Rôle supprimé',
        'service.created' => 'Service créé', 'settings.updated' => 'Réglages modifiés',
        'subscription.requested' => 'Abonnement demandé', 'subscription.activated' => 'Abonnement activé',
        'subscription.cancelled' => 'Abonnement annulé', 'subscription.expired' => 'Abonnement expiré',
        'user.suspended' => 'Compte bloqué', 'user.reactivated' => 'Compte débloqué',
        'user.admin_role' => 'Rôle d\'administration attribué', 'user.demoted_admin' => 'Droits d\'administration retirés',
        'user.email_verified_by_admin' => 'Email confirmé par l\'admin',
        'user.impersonated' => 'Accès au compte (assistance)', 'user.impersonation_ended' => 'Fin de l\'accès au compte',
        'provider.type_changed' => 'Métier d\'un prestataire changé', 'provider.shop_added' => 'Nouvelle boutique déclarée', 'shop.member_added' => 'Employé ajouté', 'shop.member_role' => 'Rôle d\'employé modifié', 'shop.member_removed' => 'Employé retiré',
        'form.field_added' => 'Champ de formulaire ajouté', 'form.field_updated' => 'Champ de formulaire modifié', 'form.field_deleted' => 'Champ de formulaire supprimé',
        'lock.added' => 'Élément verrouillé', 'lock.removed' => 'Élément déverrouillé',
    ];

    public function label(): string
    {
        if (str_starts_with($this->action, 'report.')) {
            return 'Signalement traité';
        }

        return self::LABELS[$this->action] ?? $this->action;
    }

    /** Objet concerné, lisible (« Prestataire #12 »). */
    public function subjectLabel(): string
    {
        if (! $this->subject_type) {
            return '—';
        }

        $names = ['Provider' => 'Prestataire', 'User' => 'Utilisateur', 'Product' => 'Produit', 'Review' => 'Avis', 'Report' => 'Signalement',
            'Subscription' => 'Abonnement', 'Plan' => 'Offre', 'Role' => 'Rôle', 'Page' => 'Page', 'PartRequest' => 'Demande', 'ProviderType' => 'Type', 'Service' => 'Service'];
        $base = class_basename($this->subject_type);

        return ($names[$base] ?? $base).' #'.$this->subject_id;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
