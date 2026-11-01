<?php

namespace App\Services;

use App\Enums\Availability;
use App\Models\City;
use App\Models\Neighborhood;
use App\Models\Provider;
use App\Models\ProviderType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recherche publique de prestataires, partagée par le site web et l'API.
 * Ne renvoie JAMAIS un prestataire non validé (scope visible()).
 */
class ProviderSearch
{
    /** Filtres normalisés, réutilisés par la vue pour pré-remplir le formulaire. */
    public array $filters = [];

    /** Centre de recherche effectif : ['lat','lng','label'] ou null. */
    public ?array $center = null;

    public function __construct(array $input)
    {
        $radii = static::radii();
        $default = (int) setting('default_radius');
        $radius = (int) ($input['radius'] ?? $default);

        $this->filters = [
            'q' => trim((string) ($input['q'] ?? '')),
            'type' => ProviderType::bySlug((string) ($input['type'] ?? ''))?->slug,
            'service' => $input['service'] ?? null,
            'city' => isset($input['city']) ? (int) $input['city'] : null,
            'neighborhood' => isset($input['neighborhood']) ? (int) $input['neighborhood'] : null,
            'lat' => is_numeric($input['lat'] ?? null) ? (float) $input['lat'] : null,
            'lng' => is_numeric($input['lng'] ?? null) ? (float) $input['lng'] : null,
            'radius' => in_array($radius, $radii, true) ? $radius : $default,
            'open_now' => filter_var($input['open_now'] ?? false, FILTER_VALIDATE_BOOL),
            'night' => filter_var($input['night'] ?? false, FILTER_VALIDATE_BOOL),
            'verified' => filter_var($input['verified'] ?? false, FILTER_VALIDATE_BOOL),
            'electric' => filter_var($input['electric'] ?? false, FILTER_VALIDATE_BOOL),
            'available' => filter_var($input['available'] ?? false, FILTER_VALIDATE_BOOL),
        ];

        $this->resolveCenter();
    }

    /** Rayons proposés (réglage admin), toujours triés et incluant le rayon par défaut. */
    public static function radii(): array
    {
        $radii = array_map('intval', (array) setting('search_radii'));
        $radii[] = (int) setting('default_radius');
        $radii = array_values(array_unique(array_filter($radii, fn ($r) => $r > 0)));
        sort($radii);

        return $radii;
    }

    private function resolveCenter(): void
    {
        $f = $this->filters;

        if ($f['lat'] !== null && $f['lng'] !== null
            && abs($f['lat']) <= 90 && abs($f['lng']) <= 180) {
            $this->center = ['lat' => $f['lat'], 'lng' => $f['lng'], 'label' => 'Ma position'];

            return;
        }

        if ($f['neighborhood'] && $n = Neighborhood::with('city')->find($f['neighborhood'])) {
            $this->center = ['lat' => $n->latitude, 'lng' => $n->longitude, 'label' => $n->name.', '.$n->city->name];
            $this->filters['city'] = $n->city_id;

            return;
        }

        if ($f['city'] && $c = City::find($f['city'])) {
            // Ville seule : on centre la carte, mais on filtre par ville plutôt que par rayon.
            $this->center = ['lat' => $c->latitude, 'lng' => $c->longitude, 'label' => $c->name, 'city_only' => true];
        }
    }

    public function query(): Builder
    {
        $f = $this->filters;

        $query = Provider::query()
            ->visible()
            ->with(['services' => fn ($q) => $q->active()->select('services.id', 'services.name'), 'neighborhood:id,name', 'city:id,name', 'schedules']);

        // Offre de visibilité : les fiches « à la une » passent en tête quand l'option est active.
        if (feature('featured_enabled')) {
            $query->orderByDesc('providers.is_featured');
        }

        if ($this->center && empty($this->center['city_only'])) {
            $query->near($this->center['lat'], $this->center['lng'], $f['radius'])
                ->orderBy('distance_m');
        } else {
            if ($f['city']) {
                $query->where('providers.city_id', $f['city']);
            }
            // Sans position : les plus fiables d'abord.
            $query->orderByDesc('is_formal')->orderByDesc('rating_avg')->orderByDesc('reviews_count');
        }

        if ($f['type']) {
            $query->where('providers.type', $f['type']);
        }

        if ($f['service']) {
            $query->whereHas('services', fn (Builder $q) => $q->where('slug', $f['service']));
        }

        if ($f['q'] !== '') {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $f['q']).'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('business_name', 'ilike', $term)
                    ->orWhere('description', 'ilike', $term)
                    ->orWhereHas('services', fn (Builder $s) => $s->where('name', 'ilike', $term));
            });
        }

        if ($f['verified']) {
            $query->whereNotNull('identity_verified_at');
        }
        if ($f['electric']) {
            $query->whereNotNull('ev_verified_at');
        }
        if ($f['night']) {
            $query->where('night_service', true);
        }

        if ($f['available']) {
            $query->where('availability', Availability::Available);
        }

        if ($f['open_now']) {
            $query->openAt(now());
        }

        return $query;
    }

    public function paginate(): LengthAwarePaginator
    {
        return $this->query()->paginate(max(5, (int) setting('per_page')))->withQueryString();
    }
}
