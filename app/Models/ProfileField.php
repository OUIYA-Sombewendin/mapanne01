<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Champ de fiche ajouté par l'administration (Référentiels › Formulaires des métiers).
 * S'affiche dans la section « Autres informations » du formulaire du métier concerné.
 */
class ProfileField extends Model
{
    public const TYPES = [
        'text' => 'Texte court',
        'textarea' => 'Texte long',
        'number' => 'Nombre',
        'bool' => 'Oui / non',
        'select' => 'Liste (un choix)',
        'multi' => 'Cases à cocher (plusieurs choix)',
    ];

    protected $fillable = ['provider_type', 'key', 'label', 'type', 'options', 'help', 'placeholder', 'is_required', 'is_public', 'is_active', 'position'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    /** Champs actifs qui s'appliquent à ce prestataire (son type + « tous les types »). */
    public static function forProvider(Provider $provider): Collection
    {
        return static::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('provider_type')->orWhere('provider_type', $provider->getRawOriginal('type') ?? $provider->type?->slug))
            ->ordered()->get();
    }

    /** @return array<string, array> règles de validation (clés custom.<clé>) */
    public static function rulesFor(Collection $fields): array
    {
        $rules = [];
        foreach ($fields as $field) {
            $key = 'custom.'.$field->key;
            $base = [$field->is_required && $field->type !== 'bool' ? 'required' : 'nullable'];
            $rules[$key] = match ($field->type) {
                'number' => [...$base, 'numeric', 'min:0', 'max:1000000000'],
                'bool' => ['nullable', 'boolean'],
                'select' => [...$base, Rule::in($field->options ?? [])],
                'multi' => [...$base, 'array'],
                'textarea' => [...$base, 'string', 'max:2000'],
                default => [...$base, 'string', 'max:200'],
            };
            if ($field->type === 'multi') {
                $rules[$key.'.*'] = [Rule::in($field->options ?? [])];
            }
        }

        return $rules;
    }

    public function cast(mixed $raw): mixed
    {
        return match ($this->type) {
            'bool' => (bool) $raw,
            'number' => $raw === null || $raw === '' ? null : $raw + 0,
            'multi' => array_values((array) $raw),
            default => blank($raw) ? null : trim((string) $raw),
        };
    }

    /** Valeur lisible pour la fiche publique ou l'admin (null si vide). */
    public function display(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === [] || $value === false) {
            return null;
        }

        return match ($this->type) {
            'bool' => 'Oui',
            'multi' => implode(', ', (array) $value),
            'number' => number_format((float) $value, floor((float) $value) == (float) $value ? 0 : 1, ',', ' '),
            default => (string) $value,
        };
    }

    public function targetLabel(): string
    {
        return $this->provider_type ? (ProviderType::bySlug($this->provider_type)?->label() ?? $this->provider_type) : 'Tous les métiers';
    }
}
