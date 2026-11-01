<?php

namespace App\Services;

use App\Enums\VehicleType;
use App\Models\City;
use App\Models\Neighborhood;
use App\Models\PartCategory;
use App\Models\Product;
use App\Models\ProductFitment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recherche publique de pièces, partagée par le site web et l'API.
 * Ne renvoie que des produits visibles (boutique validée, produit actif et non modéré).
 */
class ProductSearch
{
    public const SORTS = [
        'relevance' => 'Pertinence',
        'distance' => 'Plus proches',
        'price_asc' => 'Prix croissant',
        'price_desc' => 'Prix décroissant',
        'recent' => 'Plus récents',
    ];

    public array $filters = [];

    /** Centre de recherche (position, quartier ou ville), ou null. */
    public ?array $center = null;

    public function __construct(array $input)
    {
        $sort = (string) ($input['sort'] ?? '');

        $this->filters = [
            'q' => trim((string) ($input['q'] ?? '')),
            'category' => is_string($input['category'] ?? null) ? $input['category'] : null,
            'vehicle' => VehicleType::tryFrom((string) ($input['vehicle'] ?? ''))?->value,
            // Véhicule précis : marque, modèle, année (compatibilité structurée).
            'make' => is_string($input['make'] ?? null) && trim($input['make']) !== '' ? mb_substr(trim($input['make']), 0, 40) : null,
            'model' => is_string($input['model'] ?? null) && trim($input['model']) !== '' ? mb_substr(trim($input['model']), 0, 60) : null,
            'year' => is_numeric($input['year'] ?? null) && $input['year'] >= 1960 && $input['year'] <= 2100 ? (int) $input['year'] : null,
            'condition' => in_array($input['condition'] ?? null, array_keys(Product::CONDITIONS), true) ? $input['condition'] : null,
            'in_stock' => filter_var($input['in_stock'] ?? false, FILTER_VALIDATE_BOOL),
            'price_min' => is_numeric($input['price_min'] ?? null) ? max(0, (int) $input['price_min']) : null,
            'price_max' => is_numeric($input['price_max'] ?? null) ? max(0, (int) $input['price_max']) : null,
            'city' => isset($input['city']) && is_numeric($input['city']) ? (int) $input['city'] : null,
            'neighborhood' => isset($input['neighborhood']) && is_numeric($input['neighborhood']) ? (int) $input['neighborhood'] : null,
            'lat' => is_numeric($input['lat'] ?? null) ? (float) $input['lat'] : null,
            'lng' => is_numeric($input['lng'] ?? null) ? (float) $input['lng'] : null,
            'shop' => is_string($input['shop'] ?? null) ? $input['shop'] : null,
            'sort' => array_key_exists($sort, self::SORTS) ? $sort : null,
        ];

        $this->resolveCenter();
        $this->filters['sort'] ??= $this->center ? 'distance' : 'relevance';
    }

    private function resolveCenter(): void
    {
        $f = $this->filters;

        if ($f['lat'] !== null && $f['lng'] !== null && abs($f['lat']) <= 90 && abs($f['lng']) <= 180) {
            $this->center = ['lat' => $f['lat'], 'lng' => $f['lng'], 'label' => 'Ma position'];
        } elseif ($f['neighborhood'] && $n = Neighborhood::with('city')->find($f['neighborhood'])) {
            $this->center = ['lat' => $n->latitude, 'lng' => $n->longitude, 'label' => $n->name.', '.$n->city->name];
            $this->filters['city'] = $n->city_id;
        } elseif ($f['city'] && $c = City::find($f['city'])) {
            $this->center = ['lat' => $c->latitude, 'lng' => $c->longitude, 'label' => $c->name];
        }
    }

    public function category(): ?PartCategory
    {
        return $this->filters['category'] ? PartCategory::firstWhere('slug', $this->filters['category']) : null;
    }

    public function query(): Builder
    {
        $f = $this->filters;

        $query = Product::query()
            ->visible()
            ->select('products.*')
            ->join('providers', 'providers.id', '=', 'products.provider_id')
            ->with(['photos', 'fitments', 'category:id,name,icon', 'provider' => fn ($q) => $q->with('neighborhood:id,name', 'city:id,name')]);

        if ($this->center) {
            $query->selectRaw(
                'ST_Distance(providers.location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) AS distance_m',
                [$this->center['lng'], $this->center['lat']]
            );
        }

        if ($f['city']) {
            $query->where('providers.city_id', $f['city']);
        }

        if ($f['shop']) {
            $query->where('providers.slug', $f['shop']);
        }

        if ($category = $this->category()) {
            $query->whereIn('products.part_category_id', $category->familyIds());
        }

        if ($f['make']) {
            // Pièces déclarées compatibles, ou anciennes fiches dont le texte libre cite la marque (et le modèle).
            $query->where(fn (Builder $q) => $q
                ->whereHas('fitments', fn (Builder $fit) => ProductFitment::applyMatch($fit, $f['make'], $f['model'], $f['year']))
                ->orWhere(fn (Builder $legacy) => $legacy
                    ->whereDoesntHave('fitments')
                    ->where('products.compatibility', 'ilike', '%'.$this->escape($f['make']).'%')
                    ->when($f['model'], fn ($l) => $l->where('products.compatibility', 'ilike', '%'.$this->escape($f['model']).'%'))));
        }

        if ($f['vehicle']) {
            $query->whereJsonContains('products.vehicle_types', $f['vehicle']);
        }

        if ($f['condition']) {
            $query->where('products.condition', $f['condition']);
        }

        if ($f['in_stock']) {
            $query->where(fn (Builder $q) => $q->where('products.stock', '>', 0)->orWhere('products.on_order', true));
        }

        if ($f['price_min'] !== null) {
            $query->where('products.price', '>=', $f['price_min']);
        }

        if ($f['price_max'] !== null) {
            $query->where('products.price', '<=', $f['price_max']);
        }

        // Recherche texte : chaque mot doit apparaître dans le nom, la marque, la référence
        // ou la compatibilité (« plaquette corolla » trouve « Plaquettes de frein … Toyota Corolla »).
        foreach (array_slice(preg_split('/\s+/', $f['q'], -1, PREG_SPLIT_NO_EMPTY), 0, 5) as $word) {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $word).'%';
            $query->where(fn (Builder $q) => $q
                ->where('products.name', 'ilike', $term)
                ->orWhere('products.brand', 'ilike', $term)
                ->orWhere('products.reference', 'ilike', $term)
                ->orWhere('products.barcode', $word)
                ->orWhere('products.compatibility', 'ilike', $term)
                ->orWhereHas('fitments', fn (Builder $fit) => $fit->where(fn (Builder $w) => $w->where('make', 'ilike', $term)->orWhere('model', 'ilike', $term)))
                ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'ilike', $term)));
        }

        match ($f['sort']) {
            'distance' => $this->center ? $query->orderBy('distance_m') : $query->latest('products.created_at'),
            'price_asc' => $query->orderBy('products.price'),
            'price_desc' => $query->orderByDesc('products.price'),
            'recent' => $query->latest('products.created_at'),
            // Pertinence : produits « à la une » (si l'option est active), disponibles,
            // puis boutiques formelles et bien notées.
            default => $query
                ->when(feature('featured_enabled'), fn ($q) => $q->orderByDesc('products.is_featured')->orderByDesc('providers.is_featured'))
                ->orderByRaw('(products.stock > 0 OR products.on_order) DESC')
                ->orderByDesc('providers.is_formal')
                ->orderByDesc('providers.rating_avg')
                ->latest('products.created_at'),
        };

        return $query;
    }

    private function escape(string $value): string
    {
        return str_replace(['%', '_'], ['\%', '\_'], $value);
    }

    public function paginate(int $perPage = 24): LengthAwarePaginator
    {
        return $this->query()->paginate($perPage)->withQueryString();
    }
}
