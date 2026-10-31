<?php

namespace App\Models\Profiles;

use App\Models\Provider;
use App\Support\ProviderProfiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fiche métier (une table par catégorie). Les champs et leurs types sont décrits
 * dans App\Support\ProviderProfiles ; les casts en sont déduits.
 */
abstract class TypeProfile extends Model
{
    /** Clé de la catégorie dans ProviderProfiles::kinds(). */
    public const KIND = '';

    protected $guarded = ['id', 'provider_id'];

    protected function casts(): array
    {
        return collect(ProviderProfiles::kinds()[static::KIND]['fields'])
            ->map(fn ($field) => match ($field['type']) {
                'bool' => 'boolean',
                'int' => 'integer',
                'decimal' => 'float',
                'multi' => 'array',
                default => 'string',
            })->all();
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /** @return array{chips: list<string>, facts: list<string>} */
    public function present(): array
    {
        return ProviderProfiles::present(static::KIND, $this);
    }
}
