<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Recherche « par problème » : un conducteur écrit « ma moto ne démarre pas » plutôt que
 * « mécanicien moto ». Les mots reconnus mènent aux services qui règlent ce problème.
 */
class SearchProblems
{
    /** clé => [libellé, icône, mots-clés (sans accents), services (slugs)] */
    public const ALL = [
        'crevaison' => ['Crevaison, pneu', '🛞', ['crev', 'pneu', 'roue', 'chambre a air', 'vulcan', 'degonfl'], ['crevaison-vulcanisation', 'changement-de-pneu', 'gonflage']],
        'demarrage' => ['Ne démarre pas', '🔑', ['demarre', 'demarrage', 'demarrer', 'batterie', 'cle', 'kick'], ['batterie-recharge-remplacement', 'electricite-automobile', 'mecanique-generale', 'mecanique-moto', 'depannage-sur-place']],
        'freins' => ['Freins', '🛑', ['frein', 'plaquette', 'freine'], ['freinage']],
        'surchauffe' => ['Surchauffe, fumée', '🌡️', ['chauffe', 'radiateur', 'fumee', 'fume', 'temperature'], ['diagnostic-moteur', 'mecanique-generale', 'mecanique-moto']],
        'moteur' => ['Bruit, moteur, voyant', '🔧', ['bruit', 'moteur', 'voyant', 'cale', 'cogne', 'tousse', 'perd de la puissance'], ['diagnostic-moteur', 'mecanique-generale', 'mecanique-moto']],
        'electricite' => ['Phares, électricité', '💡', ['phare', 'lumiere', 'feu ', 'electri', 'fusible', 'klaxon', 'clignotant'], ['electricite-automobile']],
        'vidange' => ['Vidange, huile', '🛢️', ['vidange', 'huile'], ['vidange', 'boutique-et-lubrifiants']],
        'embrayage' => ['Embrayage, vitesses', '⚙️', ['embray', 'boite', 'vitesse', 'chaine'], ['embrayage-et-boite', 'mecanique-moto']],
        'clim' => ['Climatisation', '❄️', ['clim'], ['climatisation']],
        'accident' => ['Accident, remorquage', '🚛', ['accident', 'remorqu', 'bloque', 'en panne', 'depann', 'transport'], ['remorquage', 'depannage-sur-place']],
        'carrosserie' => ['Carrosserie, vitre', '🎨', ['carross', 'tole', 'rayure', 'peinture', 'pare-brise', 'pare brise', 'vitre', 'retroviseur'], ['carrosserie-et-tolerie', 'peinture', 'vitrage']],
    ];

    /** Mots désignant un type de véhicule dans une recherche libre. */
    public const VEHICLE_WORDS = ['moto' => 'moto', 'scooter' => 'moto', 'tricycle' => 'tricycle', 'tricy' => 'tricycle', 'camion' => 'camion', 'voiture' => 'voiture', 'auto' => 'voiture'];

    /** Problèmes reconnus dans une phrase. @return list<string> */
    public static function detect(string $text): array
    {
        $text = ' '.Str::lower(Str::ascii($text)).' ';

        return collect(self::ALL)
            ->filter(fn ($p) => collect($p[2])->contains(fn ($word) => str_contains($text, $word)))
            ->keys()->all();
    }

    public static function vehicle(string $text): ?string
    {
        $words = preg_split('/[^a-z]+/', Str::lower(Str::ascii($text)), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($words as $word) {
            if (isset(self::VEHICLE_WORDS[$word])) {
                return self::VEHICLE_WORDS[$word];
            }
        }

        return null;
    }

    /** @param list<string> $problems */
    public static function services(array $problems): array
    {
        return collect($problems)->flatMap(fn ($p) => self::ALL[$p][3] ?? [])->unique()->values()->all();
    }

    public static function label(string $problem): string
    {
        return (self::ALL[$problem][1] ?? '').' '.(self::ALL[$problem][0] ?? $problem);
    }
}
