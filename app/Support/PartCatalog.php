<?php

namespace App\Support;

use App\Models\PartCategory;
use Illuminate\Support\Str;

/**
 * Référentiel des pièces : sous-catégories proposées par défaut, unités de vente
 * et marques de véhicules courantes au Burkina Faso (suggestions de saisie).
 */
class PartCatalog
{
    /** Catégorie parente (slug) => sous-catégories [nom, icône]. */
    public const SUBCATEGORIES = [
        'freinage' => [['Plaquettes de frein', '🛑'], ['Disques et tambours', '💿'], ['Mâchoires', '🛑'], ['Câbles et flexibles de frein', '🔗']],
        'pieces-moteur' => [['Courroies', '➰'], ['Joints moteur', '⭕'], ['Bougies', '⚡'], ['Pistons et segments', '🔩']],
        'lubrifiants-et-filtres' => [['Huiles moteur', '🛢️'], ['Filtres à huile', '🧴'], ['Filtres à air', '🌬️'], ['Filtres à carburant', '⛽']],
        'electricite-et-batterie' => [['Batteries', '🔋'], ['Alternateurs et démarreurs', '⚙️'], ['Fusibles et relais', '🔌']],
        'suspension-et-direction' => [['Amortisseurs', '🛞'], ['Rotules et biellettes', '🔗'], ['Roulements', '⚙️']],
        'pieces-moto-et-tricycle' => [['Chaînes et pignons', '⛓️'], ['Kits embrayage moto', '⚙️'], ['Carburateurs', '🔧'], ['Pneus et chambres à air moto', '⭕']],
    ];

    /** Unités de vente : clé => [singulier, pluriel]. */
    public const UNITS = [
        'piece' => ['pièce', 'pièces'],
        'paire' => ['paire', 'paires'],
        'jeu' => ['jeu', 'jeux'],
        'kit' => ['kit', 'kits'],
        'litre' => ['litre', 'litres'],
        'bidon' => ['bidon', 'bidons'],
        'boite' => ['boîte', 'boîtes'],
        'metre' => ['mètre', 'mètres'],
    ];

    public const MAKES = [
        // Voitures et camions
        'Toyota', 'Nissan', 'Mercedes-Benz', 'Peugeot', 'Renault', 'Hyundai', 'Kia', 'Mitsubishi', 'Honda', 'Ford',
        'Volkswagen', 'Mazda', 'Isuzu', 'Suzuki', 'Opel', 'Citroën', 'BMW', 'Land Rover', 'Iveco', 'DAF', 'MAN',
        // Motos et tricycles
        'Yamaha', 'Haojue', 'Sanili', 'Apsonic', 'Jincheng', 'Dayun', 'Lifan', 'TVS', 'Bajaj', 'KTM', 'Kymco', 'Piaggio', 'Royal',
    ];

    /** Écrit une marque comme dans la liste de référence (« toyota » → « Toyota »). */
    public static function make(string $make): string
    {
        $make = trim(preg_replace('/\s+/', ' ', $make));
        foreach (self::MAKES as $known) {
            if (mb_strtolower($known) === mb_strtolower($make)) {
                return $known;
            }
        }

        return mb_strtoupper(mb_substr($make, 0, 1)).mb_substr($make, 1);
    }

    public static function unitLabel(?string $unit, int $quantity = 1): string
    {
        $labels = self::UNITS[$unit ?? 'piece'] ?? self::UNITS['piece'];

        return $quantity > 1 ? $labels[1] : $labels[0];
    }

    /** Crée les sous-catégories par défaut sous les catégories existantes (sans doublon). */
    public static function seedSubcategories(): void
    {
        foreach (self::SUBCATEGORIES as $parentSlug => $children) {
            $parent = PartCategory::whereNull('parent_id')->firstWhere('slug', $parentSlug);
            if (! $parent) {
                continue;
            }
            foreach ($children as $position => [$name, $icon]) {
                $slug = Str::slug($name);
                if (PartCategory::where('slug', $slug)->exists()) {
                    continue;
                }
                PartCategory::create(['name' => $name, 'slug' => $slug, 'icon' => $icon, 'position' => $position, 'parent_id' => $parent->id]);
            }
        }
    }
}
