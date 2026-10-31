<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Verrou anti-erreur posé sur un élément (voir le trait Concerns\Lockable). */
class Lock extends Model
{
    public const UPDATED_AT = null;

    /** Éléments verrouillables : clé utilisée dans les formulaires => [classe, libellé]. */
    public const TYPES = [
        'provider' => [Provider::class, 'Prestataire'],
        'user' => [User::class, 'Compte'],
        'product' => [Product::class, 'Produit'],
        'review' => [Review::class, 'Avis'],
        'type' => [ProviderType::class, 'Type de prestataire'],
        'service' => [Service::class, 'Service'],
        'city' => [City::class, 'Ville'],
        'page' => [Page::class, 'Page'],
        'plan' => [Plan::class, 'Offre'],
        'role' => [Role::class, 'Rôle'],
    ];

    protected $fillable = ['locked_by', 'reason'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function lockable(): MorphTo
    {
        return $this->morphTo();
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public static function keyFor(Model $model): ?string
    {
        foreach (self::TYPES as $key => [$class]) {
            if ($model instanceof $class) {
                return $key;
            }
        }

        return null;
    }

    public function typeLabel(): string
    {
        $key = collect(self::TYPES)->search(fn ($t) => (new $t[0])->getMorphClass() === $this->lockable_type);

        return $key ? self::TYPES[$key][1] : class_basename($this->lockable_type);
    }

    /** Nom lisible de l'élément verrouillé. */
    public function lockableName(): string
    {
        $m = $this->lockable;

        return $m ? (string) ($m->business_name ?? $m->name ?? $m->title ?? ('#'.$m->getKey())) : 'élément supprimé';
    }
}
