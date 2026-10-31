<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Recherche par mots rapide même avec des dizaines de milliers de fiches et de pièces :
 * index trigrammes (pg_trgm) utilisés par les recherches ILIKE '%mot%'.
 * Si l'extension ne peut pas être créée (droits insuffisants chez l'hébergeur), la recherche
 * fonctionne quand même, simplement sans ces index.
 */
return new class extends Migration
{
    private const INDEXES = [
        'providers_business_name_trgm' => ['providers', 'business_name'],
        'providers_description_trgm' => ['providers', 'description'],
        'services_name_trgm' => ['services', 'name'],
        'products_name_trgm' => ['products', 'name'],
        'products_brand_trgm' => ['products', 'brand'],
        'products_reference_trgm' => ['products', 'reference'],
        'products_compatibility_trgm' => ['products', 'compatibility'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        } catch (Throwable $e) {
            logger()->warning('pg_trgm indisponible : index de recherche non créés. '.$e->getMessage());

            return;
        }

        foreach (self::INDEXES as $name => [$table, $column]) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON {$table} USING gin ({$column} gin_trgm_ops)");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        foreach (array_keys(self::INDEXES) as $name) {
            DB::statement("DROP INDEX IF EXISTS {$name}");
        }
    }
};
