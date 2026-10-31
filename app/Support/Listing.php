<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * Tri et filtres communs à toutes les listes (admin et pro).
 * Le tri vient des en-têtes de colonnes (?sort=colonne&dir=asc|desc) ; seules les colonnes déclarées sont acceptées.
 */
class Listing
{
    /**
     * @param  array<string, string|Closure>  $columns  clé de tri => colonne SQL, ou fonction (query, direction)
     */
    public static function sort(Builder|Relation|QueryBuilder $query, Request $request, array $columns, string $default, string $defaultDir = 'desc'): Builder|Relation|QueryBuilder
    {
        $key = (string) $request->query('sort');
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        if (! isset($columns[$key])) {
            [$key, $dir] = [$default, $defaultDir];
        }

        $column = $columns[$key];
        if ($column instanceof Closure) {
            $column($query, $dir);
        } else {
            // Les valeurs vides vont toujours en fin de liste.
            $query->orderByRaw("{$column} {$dir} NULLS LAST");
        }

        // Ordre stable entre deux pages.
        return $query->orderBy($query->getModel()->getQualifiedKeyName(), 'desc');
    }

    /** Motif ILIKE échappé pour une recherche « contient ». */
    public static function like(string $q): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q).'%';
    }

    /** Valeur de filtre « oui / non » : true, false ou null (non filtré). */
    public static function flag(Request $request, string $key): ?bool
    {
        return match ((string) $request->query($key)) {
            '1', 'oui' => true,
            '0', 'non' => false,
            default => null,
        };
    }
}
