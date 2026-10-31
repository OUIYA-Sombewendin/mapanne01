<?php

namespace App\Support;

use App\Enums\VehicleType;
use App\Models\PartCategory;
use App\Models\Provider;
use Illuminate\Support\Str;

/**
 * Import du catalogue d'une boutique depuis un fichier CSV (séparateur ; ou ,).
 * Une ligne dont le nom existe déjà dans la boutique met à jour le produit (prix, stock…).
 */
class CatalogImporter
{
    public const MAX_ROWS = 1000;

    /** colonne => description (affichée sur la page d'import) */
    public const COLUMNS = [
        'nom' => 'Nom de la pièce (obligatoire)',
        'categorie' => 'Catégorie : nom ou code, voir la liste (obligatoire)',
        'prix' => 'Prix en FCFA, sans espace (obligatoire)',
        'stock' => 'Quantité en stock (0 si sur commande)',
        'marque' => 'Marque de la pièce',
        'reference' => 'Référence constructeur',
        'compatibilite' => 'Modèles compatibles',
        'vehicules' => 'voiture, moto, tricycle, camion (séparés par des virgules)',
        'etat' => 'neuf ou occasion',
        'prix_barre' => 'Ancien prix (promotion)',
        'garantie' => 'Ex. 3 mois',
        'description' => 'Description',
        'prix_achat' => 'Prix d\'achat (jamais affiché aux clients)',
        'emplacement' => 'Emplacement en boutique (ex. Rayon A)',
    ];

    private array $categories;

    public function __construct(private Provider $shop)
    {
        // Correspondance par code (slug) ou par nom, sans tenir compte des accents ni de la casse.
        $this->categories = PartCategory::all()->flatMap(fn ($c) => [
            $c->slug => $c->id,
            Str::slug($c->name) => $c->id,
        ])->all();
    }

    /** @return array{created:int, updated:int, errors:list<string>} */
    public function import(string $path): array
    {
        $result = ['created' => 0, 'updated' => 0, 'errors' => []];
        $handle = fopen($path, 'r');
        if (! $handle) {
            $result['errors'][] = 'Fichier illisible.';

            return $result;
        }

        $first = (string) fgets($handle);
        $first = preg_replace('/^\x{FEFF}/u', '', $first);
        $delimiter = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
        $headers = array_map(fn ($h) => Str::slug(trim($h), '_'), str_getcsv(trim($first), $delimiter));

        if (! in_array('nom', $headers, true) || ! in_array('prix', $headers, true)) {
            fclose($handle);
            $result['errors'][] = 'Première ligne invalide : les colonnes « nom » et « prix » sont obligatoires. Utilisez le modèle.';

            return $result;
        }

        $allowUsed = feature('allow_used_parts');
        $line = 1;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            if ($line > self::MAX_ROWS + 1) {
                $result['errors'][] = 'Seules les '.self::MAX_ROWS.' premières lignes ont été traitées.';
                break;
            }
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $r = array_combine($headers, array_pad(array_slice($row, 0, count($headers)), count($headers), null));
            $r = array_map(fn ($v) => $v === null ? null : trim(mb_convert_encoding($v, 'UTF-8', 'UTF-8, Windows-1252')), $r);

            $name = $r['nom'] ?? '';
            $price = (int) preg_replace('/\D/', '', (string) ($r['prix'] ?? ''));
            $categoryId = $this->categories[Str::slug((string) ($r['categorie'] ?? ''))] ?? null;

            $problems = [];
            if ($name === '' || mb_strlen($name) > 150) {
                $problems[] = 'nom manquant';
            }
            if ($price < 50) {
                $problems[] = 'prix invalide';
            }
            if (! $categoryId) {
                $problems[] = 'catégorie inconnue « '.($r['categorie'] ?? '').' »';
            }
            if ($problems) {
                $result['errors'][] = "Ligne $line : ".implode(', ', $problems).'.';

                continue;
            }

            $types = collect(explode(',', (string) ($r['vehicules'] ?? '')))
                ->map(fn ($t) => VehicleType::tryFrom(Str::lower(trim($t)))?->value)->filter()->unique()->values()->all();
            $condition = Str::startsWith(Str::lower((string) ($r['etat'] ?? '')), 'occ') && $allowUsed ? 'used' : 'new';
            $compare = (int) preg_replace('/\D/', '', (string) ($r['prix_barre'] ?? ''));

            $attributes = [
                'part_category_id' => $categoryId,
                'price' => $price,
                'stock' => max(0, (int) ($r['stock'] ?? 0)),
                'brand' => Str::limit((string) ($r['marque'] ?? ''), 60, '') ?: null,
                'reference' => Str::limit((string) ($r['reference'] ?? ''), 60, '') ?: null,
                'compatibility' => Str::limit((string) ($r['compatibilite'] ?? ''), 255, '') ?: null,
                'vehicle_types' => $types ?: ['voiture'],
                'condition' => $condition,
                'compare_at_price' => $compare > $price ? $compare : null,
                'warranty' => Str::limit((string) ($r['garantie'] ?? ''), 60, '') ?: null,
                'description' => $r['description'] ?? null,
                'cost_price' => ($cost = (int) preg_replace('/\D/', '', (string) ($r['prix_achat'] ?? ''))) > 0 ? $cost : null,
                'shelf' => Str::limit((string) ($r['emplacement'] ?? ''), 60, '') ?: null,
            ];

            // Le stock passe par l'inventaire pour garder l'historique des mouvements.
            $stock = $attributes['stock'];
            unset($attributes['stock']);

            $existing = $this->shop->products()->where('name', $name)->first();
            if ($existing) {
                $existing->update($attributes);
                $product = $existing;
                $result['updated']++;
            } else {
                $product = $this->shop->products()->create($attributes + ['name' => $name, 'is_active' => true, 'stock' => 0]);
                $result['created']++;
            }
            if ($stock !== $product->stock) {
                Inventory::move($product, 'adjust', $stock, 'import');
            }
        }

        fclose($handle);

        return $result;
    }
}
