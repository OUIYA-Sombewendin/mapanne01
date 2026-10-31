<?php

namespace App\Casts;

use App\Models\ProviderType;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * providers.type stocke le slug ; l'attribut renvoie le modèle ProviderType (mis en cache).
 * Accepte en écriture un slug ou un modèle.
 */
class ProviderTypeCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?ProviderType
    {
        return ProviderType::bySlug($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value instanceof ProviderType ? $value->slug : $value;
    }
}
