<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Observatoire des prix : prix minimum, médian et maximum des pièces publiées par les boutiques.
 * Les noms étant saisis librement, les pièces sont rapprochées par catégorie et par ressemblance du nom (pg_trgm).
 */
class PriceWatch
{
    private const STATS = 'COUNT(*) AS products, COUNT(DISTINCT products.provider_id) AS shops,
        MIN(products.price) AS min, MAX(products.price) AS max, ROUND(AVG(products.price)) AS avg,
        ROUND(PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY products.price)) AS median';

    /** Pièces visibles, filtrées (ville, véhicule, état, recherche). */
    public static function base(array $filters = []): Builder
    {
        return Product::visible()
            ->when($filters['city'] ?? null, fn ($q, $city) => $q->whereHas('provider', fn ($p) => $p->where('city_id', $city)))
            ->when($filters['vehicle'] ?? null, fn ($q, $v) => $q->whereJsonContains('products.vehicle_types', $v))
            ->when($filters['condition'] ?? null, fn ($q, $c) => $q->where('products.condition', $c))
            ->when($filters['category'] ?? null, fn ($q, $c) => $q->whereIn('products.part_category_id', \App\Models\PartCategory::familyIdsOf($c)))
            ->when(trim((string) ($filters['q'] ?? '')) !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('products.name', 'ilike', Listing::like(trim($filters['q'])))
                ->orWhere('products.reference', 'ilike', Listing::like(trim($filters['q'])))));
    }

    /** Une ligne par catégorie. */
    public static function byCategory(array $filters = []): Collection
    {
        return self::base($filters)
            ->join('part_categories', 'part_categories.id', '=', 'products.part_category_id')
            ->selectRaw('part_categories.id, part_categories.name, part_categories.icon, '.self::STATS)
            ->groupBy('part_categories.id', 'part_categories.name', 'part_categories.icon')
            ->orderByDesc('products')
            ->toBase()->get();
    }

    /** Une ligne par pièce (même nom, à la casse près) : les plus proposées d'abord. */
    public static function byPart(array $filters = [], int $limit = 50): Collection
    {
        return self::base($filters)
            ->selectRaw('LOWER(TRIM(products.name)) AS part, MIN(products.name) AS label, MIN(products.condition) AS condition, '.self::STATS)
            ->groupByRaw('LOWER(TRIM(products.name))')
            ->orderByDesc('shops')->orderByDesc('products')
            ->limit($limit)
            ->toBase()->get();
    }

    public static function summary(array $filters = []): object
    {
        return self::base($filters)->selectRaw(self::STATS)->toBase()->first();
    }

    /**
     * Prix constaté pour une pièce proche dans d'autres boutiques (même catégorie, même état, nom ressemblant).
     * null s'il n'y a pas assez de boutiques pour que le chiffre ait un sens.
     */
    public static function forProduct(Product $product): ?object
    {
        if (! feature('price_watch_public')) {
            return null;
        }

        $stats = Product::visible()
            ->where('products.part_category_id', $product->part_category_id)
            ->where('products.condition', $product->condition)
            ->whereRaw('similarity(products.name, ?) >= 0.4', [$product->name])
            ->selectRaw(self::STATS)
            ->toBase()->first();

        return $stats && $stats->shops >= max(2, (int) setting('price_watch_min_shops', 3)) ? $stats : null;
    }
}
