<?php

namespace App\Support;

use App\Enums\VehicleType;
use App\Models\Profiles\GarageProfile;
use App\Models\Profiles\MechanicProfile;
use App\Models\Profiles\ShopProfile;
use App\Models\Profiles\StationProfile;
use App\Models\Profiles\TowTruckProfile;

/**
 * Fiches métier : les informations propres à chaque catégorie de prestataire,
 * chacune stockée dans sa propre table. Les formulaires (pro et admin) et l'affichage public
 * sont générés à partir de ces définitions.
 *
 * Types de champ : bool, int, decimal, string, multi (cases à cocher, stocké en liste).
 */
class ProviderProfiles
{
    public static function kinds(): array
    {
        $vehicles = VehicleType::options();

        return [
            'mechanic' => [
                'label' => 'Mécanicien',
                'model' => MechanicProfile::class,
                'relation' => 'mechanicProfile',
                'fields' => [
                    'is_mobile' => ['type' => 'bool', 'label' => 'Je me déplace chez le client', 'public' => '🛵 Se déplace chez le client'],
                    'travel_radius_km' => ['type' => 'int', 'label' => 'Distance max. de déplacement (km)', 'suffix' => 'km', 'max' => 500],
                    'years_experience' => ['type' => 'int', 'label' => 'Années d\'expérience', 'suffix' => 'ans d\'expérience', 'max' => 70],
                    'vehicle_types' => ['type' => 'multi', 'label' => 'Véhicules réparés', 'options' => $vehicles],
                    'specialties' => ['type' => 'multi', 'label' => 'Spécialités', 'options' => self::mechanicSpecialties()],
                    'brands' => ['type' => 'string', 'label' => 'Marques maîtrisées', 'placeholder' => 'Ex. Toyota, Yamaha, Peugeot'],
                ],
            ],
            'garage' => [
                'label' => 'Garage',
                'model' => GarageProfile::class,
                'relation' => 'garageProfile',
                'fields' => [
                    'capacity' => ['type' => 'int', 'label' => 'Places dans l\'atelier', 'suffix' => 'places', 'max' => 500],
                    'employees' => ['type' => 'int', 'label' => 'Nombre de mécaniciens', 'suffix' => 'mécaniciens', 'max' => 500],
                    'has_diagnostic' => ['type' => 'bool', 'label' => 'Valise de diagnostic électronique', 'public' => '💻 Diagnostic électronique'],
                    'has_lift' => ['type' => 'bool', 'label' => 'Pont élévateur', 'public' => '🏗️ Pont élévateur'],
                    'has_paint_shop' => ['type' => 'bool', 'label' => 'Tôlerie et peinture', 'public' => '🎨 Tôlerie et peinture'],
                    'accepts_insurance' => ['type' => 'bool', 'label' => 'Travaille avec les assurances', 'public' => '📄 Agréé assurances'],
                    'specialties' => ['type' => 'multi', 'label' => 'Spécialités', 'options' => self::mechanicSpecialties()],
                    'brands' => ['type' => 'string', 'label' => 'Marques maîtrisées', 'placeholder' => 'Ex. Toyota, Mercedes, Hyundai'],
                ],
            ],
            'tow' => [
                'label' => 'Dépanneur / remorquage',
                'model' => TowTruckProfile::class,
                'relation' => 'towTruckProfile',
                'fields' => [
                    'trucks_count' => ['type' => 'int', 'label' => 'Nombre de dépanneuses', 'suffix' => 'dépanneuse(s)', 'max' => 200],
                    'max_weight_tons' => ['type' => 'decimal', 'label' => 'Poids max. remorqué (tonnes)', 'suffix' => 't max.', 'max' => 100],
                    'vehicle_types' => ['type' => 'multi', 'label' => 'Véhicules remorqués', 'options' => $vehicles],
                    'coverage_km' => ['type' => 'int', 'label' => 'Zone d\'intervention (km)', 'suffix' => 'km autour', 'max' => 2000],
                    'base_price' => ['type' => 'int', 'label' => 'Prise en charge (FCFA)', 'suffix' => 'FCFA de prise en charge', 'max' => 10000000],
                    'price_per_km' => ['type' => 'int', 'label' => 'Prix par km (FCFA)', 'suffix' => 'FCFA / km', 'max' => 1000000],
                    'available_24h' => ['type' => 'bool', 'label' => 'Disponible 24 h/24', 'public' => '🕐 24 h/24'],
                ],
            ],
            'station' => [
                'label' => 'Station-service',
                'model' => StationProfile::class,
                'relation' => 'stationProfile',
                'fields' => [
                    'brand' => ['type' => 'string', 'label' => 'Enseigne', 'placeholder' => 'Ex. TotalEnergies, Shell, Oryx'],
                    'fuel_types' => ['type' => 'multi', 'label' => 'Carburants', 'options' => ['super' => 'Super', 'gasoil' => 'Gasoil', 'petrole' => 'Pétrole', 'gaz' => 'Gaz (bouteilles)', 'melange' => 'Mélange 2 temps']],
                    'open_24h' => ['type' => 'bool', 'label' => 'Ouverte 24 h/24', 'public' => '🕐 24 h/24'],
                    'has_shop' => ['type' => 'bool', 'label' => 'Boutique', 'public' => '🛒 Boutique'],
                    'has_car_wash' => ['type' => 'bool', 'label' => 'Lavage', 'public' => '🧽 Lavage'],
                    'has_tire_air' => ['type' => 'bool', 'label' => 'Gonflage des pneus', 'public' => '🛞 Gonflage'],
                    'has_lubricants' => ['type' => 'bool', 'label' => 'Vente de lubrifiants', 'public' => '🛢️ Lubrifiants'],
                    'has_mechanic' => ['type' => 'bool', 'label' => 'Mécanicien sur place', 'public' => '🔧 Mécanicien sur place'],
                ],
            ],
            'shop' => [
                'label' => 'Boutique de pièces',
                'model' => ShopProfile::class,
                'relation' => 'shopProfile',
                'fields' => [
                    'vehicle_types' => ['type' => 'multi', 'label' => 'Pièces pour', 'options' => $vehicles],
                    'brands' => ['type' => 'string', 'label' => 'Marques de véhicules couvertes', 'placeholder' => 'Ex. Toyota, Honda, Yamaha'],
                    'sells_retail' => ['type' => 'bool', 'label' => 'Vente au détail', 'public' => '🧍 Détail'],
                    'sells_wholesale' => ['type' => 'bool', 'label' => 'Vente en gros', 'public' => '📦 Vente en gros'],
                    'sells_used' => ['type' => 'bool', 'label' => 'Pièces d\'occasion', 'public' => '♻️ Occasion'],
                    'orders_parts' => ['type' => 'bool', 'label' => 'Commande les pièces absentes', 'public' => '📋 Commande sur demande'],
                ],
            ],
        ];
    }

    public static function mechanicSpecialties(): array
    {
        return [
            'moteur' => 'Moteur', 'electricite' => 'Électricité', 'injection' => 'Injection',
            'boite' => 'Boîte de vitesses', 'freinage' => 'Freinage', 'climatisation' => 'Climatisation',
            'diesel' => 'Diesel', 'pneumatique' => 'Pneus', 'soudure' => 'Soudure',
        ];
    }

    /** @return array<string, string> kind => libellé (pour l'admin des types) */
    public static function options(): array
    {
        return array_map(fn ($k) => $k['label'], self::kinds());
    }

    public static function get(?string $kind): ?array
    {
        return $kind ? (self::kinds()[$kind] ?? null) : null;
    }

    /** Règles de validation, préfixées par `details.`. */
    public static function rules(string $kind): array
    {
        $rules = [];
        foreach (self::kinds()[$kind]['fields'] as $name => $field) {
            $key = 'details.'.$name;
            match ($field['type']) {
                'bool' => $rules[$key] = ['nullable', 'boolean'],
                'int' => $rules[$key] = ['nullable', 'integer', 'min:0', 'max:'.($field['max'] ?? 1000000)],
                'decimal' => $rules[$key] = ['nullable', 'numeric', 'min:0', 'max:'.($field['max'] ?? 1000000)],
                'multi' => [$rules[$key] = ['nullable', 'array'], $rules[$key.'.*'] = ['string', 'in:'.implode(',', array_keys($field['options']))]],
                default => $rules[$key] = ['nullable', 'string', 'max:200'],
            };
        }

        return $rules;
    }

    /** Valeurs propres à enregistrer à partir de la saisie validée. */
    public static function values(string $kind, array $input): array
    {
        $values = [];
        foreach (self::kinds()[$kind]['fields'] as $name => $field) {
            $raw = $input[$name] ?? null;
            $values[$name] = match ($field['type']) {
                'bool' => (bool) $raw,
                'int' => $raw === null || $raw === '' ? null : (int) $raw,
                'decimal' => $raw === null || $raw === '' ? null : (float) $raw,
                'multi' => array_values((array) $raw),
                default => blank($raw) ? null : trim((string) $raw),
            };
        }

        return $values;
    }

    /**
     * Lignes lisibles pour la fiche publique.
     *
     * @return array{chips: list<string>, facts: list<string>}
     */
    public static function present(string $kind, $profile): array
    {
        $chips = [];
        $facts = [];
        foreach (self::kinds()[$kind]['fields'] as $name => $field) {
            $value = $profile->{$name};
            if (blank($value) || $value === false) {
                continue;
            }
            match ($field['type']) {
                'bool' => $chips[] = $field['public'] ?? $field['label'],
                'multi' => $facts[] = $field['label'].' : '.collect($value)->map(fn ($v) => $field['options'][$v] ?? $v)->implode(', '),
                'int', 'decimal' => $facts[] = (isset($field['suffix']) ? number_format((float) $value, $field['type'] === 'decimal' ? 1 : 0, ',', ' ').' '.$field['suffix'] : $field['label'].' : '.$value),
                default => $facts[] = $field['label'].' : '.$value,
            };
        }

        return ['chips' => $chips, 'facts' => $facts];
    }
}
