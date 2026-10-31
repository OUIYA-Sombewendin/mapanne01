<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\City;
use App\Models\Neighborhood;
use App\Models\PartCategory;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Support\CatalogImporter;
use App\Support\Csv;
use App\Support\Phone;
use App\Support\SettingsRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Import / export de données. Les exports sont des CSV lisibles dans Excel ;
 * les réglages s'exportent en JSON pour passer d'un serveur à l'autre.
 * Chaque jeu de données exige aussi la permission métier correspondante.
 */
class DataController extends Controller
{
    /** jeu => [libellé, permission métier] */
    private const EXPORTS = [
        'prestataires' => ['Prestataires (toutes les fiches)', 'providers.view'],
        'utilisateurs' => ['Utilisateurs (sans mot de passe)', 'users.view'],
        'produits' => ['Produits du marketplace', 'marketplace.manage'],
        'avis' => ['Avis clients', 'moderation.manage'],
        'abonnements' => ['Abonnements', 'subscriptions.manage'],
        'zones' => ['Villes et quartiers', 'referentials.manage'],
        'reglages' => ['Réglages (fichier JSON)', 'settings.manage'],
    ];

    /** jeu => [libellé, permission métier, colonnes attendues] */
    private const IMPORTS = [
        'zones' => ['Villes et quartiers', 'referentials.manage', 'ville;quartier;latitude;longitude'],
        'services' => ['Services', 'referentials.manage', 'categorie;service'],
        'categories-pieces' => ['Catégories de pièces', 'referentials.manage', 'nom;icone'],
        'catalogue' => ['Catalogue d\'une boutique', 'marketplace.manage', 'voir le modèle de l\'espace pro'],
        'reglages' => ['Réglages (fichier JSON exporté)', 'settings.manage', 'fichier .json'],
    ];

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->can('data.export') || $user->can('data.import'), 403);

        return view('admin.data', [
            'exports' => $user->can('data.export') ? array_filter(self::EXPORTS, fn ($e) => $user->can($e[1])) : [],
            'imports' => $user->can('data.import') ? array_filter(self::IMPORTS, fn ($e) => $user->can($e[1])) : [],
            'shops' => Provider::whereIn('type', ProviderType::sellingSlugs())->orderBy('business_name')->get(['id', 'business_name']),
        ]);
    }

    public function export(Request $request, string $dataset): Response
    {
        abort_unless(isset(self::EXPORTS[$dataset]), 404);
        abort_unless($request->user()->can(self::EXPORTS[$dataset][1]), 403);
        ActivityLog::record('data.exported', null, ['jeu' => $dataset]);
        $date = now()->format('Ymd');

        return match ($dataset) {
            'prestataires' => Csv::download("prestataires-$date.csv",
                ['ID', 'Nom', 'Type', 'Statut', 'Téléphone', 'WhatsApp', 'Email', 'Ville', 'Quartier', 'Adresse', 'Latitude', 'Longitude', 'RCCM', 'IFU', 'Nuit', 'Note', 'Avis', 'À la une', 'Offre', 'Inscrit le', 'Validé le'],
                Provider::with(['user:id,email', 'city:id,name', 'neighborhood:id,name'])->lazyById(500)->map(fn (Provider $p) => [
                    $p->id, $p->business_name, $p->type?->label(), $p->status->label(), Phone::format($p->phone), Phone::format($p->whatsapp), $p->user?->email,
                    $p->city?->name, $p->neighborhood?->name, $p->address, $p->latitude, $p->longitude, $p->rccm, $p->ifu, $p->night_service,
                    $p->rating_avg, $p->reviews_count, $p->is_featured, $p->currentPlan()?->name, $p->created_at?->format('d/m/Y'), $p->approved_at?->format('d/m/Y'),
                ])),
            'utilisateurs' => Csv::download("utilisateurs-$date.csv",
                ['ID', 'Nom', 'Email', 'Email confirmé', 'Téléphone', 'Rôle', 'Actif', 'Inscrit le', 'Dernière connexion'],
                User::with('adminRole:id,name')->lazyById(500)->map(fn (User $u) => [
                    $u->id, $u->name, $u->email, $u->email_verified_at !== null, Phone::format($u->phone),
                    $u->adminRole?->name ?? $u->role->label(), $u->is_active, $u->created_at?->format('d/m/Y'), $u->last_login_at?->format('d/m/Y H:i'),
                ])),
            'produits' => Csv::download("produits-$date.csv",
                ['ID', 'Produit', 'Boutique', 'Catégorie', 'Marque', 'Référence', 'Prix', 'Stock', 'État', 'Publié', 'Masqué', 'Vues', 'Contacts', 'Créé le'],
                Product::with(['provider:id,business_name', 'category:id,name'])->lazyById(500)->map(fn (Product $p) => [
                    $p->id, $p->name, $p->provider?->business_name, $p->category?->name, $p->brand, $p->reference, $p->price, $p->stock,
                    Product::CONDITIONS[$p->condition] ?? $p->condition, $p->is_active, $p->is_hidden, $p->views_count, $p->contacts_count, $p->created_at?->format('d/m/Y'),
                ])),
            'avis' => Csv::download("avis-$date.csv",
                ['ID', 'Prestataire', 'Client', 'Note', 'Commentaire', 'Masqué', 'En attente', 'Date'],
                Review::with(['provider:id,business_name', 'user:id,name'])->lazyById(500)->map(fn (Review $r) => [
                    $r->id, $r->provider?->business_name, $r->user?->name, $r->rating, $r->comment, $r->is_hidden, $r->is_pending, $r->created_at?->format('d/m/Y'),
                ])),
            'abonnements' => Csv::download("abonnements-$date.csv",
                ['ID', 'Prestataire', 'Offre', 'Statut', 'Début', 'Fin', 'Montant', 'Paiement', 'Référence'],
                Subscription::with(['provider:id,business_name', 'plan:id,name'])->lazyById(500)->map(fn (Subscription $s) => [
                    $s->id, $s->provider?->business_name, $s->plan?->name, $s->statusLabel(), $s->starts_at?->format('d/m/Y'), $s->ends_at?->format('d/m/Y'),
                    $s->amount, $s->payment_method, $s->payment_reference,
                ])),
            'zones' => Csv::download("zones-$date.csv", ['ville', 'quartier', 'latitude', 'longitude'],
                City::with('neighborhoods')->orderBy('name')->get()->flatMap(fn (City $c) => $c->neighborhoods->isEmpty()
                    ? [[$c->name, '', $c->latitude, $c->longitude]]
                    : $c->neighborhoods->map(fn ($n) => [$c->name, $n->name, $n->latitude, $n->longitude]))),
            'reglages' => response()->streamDownload(function () {
                // Les secrets (mot de passe SMTP) ne sont jamais exportés.
                $secrets = array_keys(array_filter(SettingsRegistry::all(), fn ($d) => $d['type'] === 'secret'));
                echo json_encode(collect(Setting::values())->except($secrets)->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }, "reglages-$date.json", ['Content-Type' => 'application/json']),
        };
    }

    public function import(Request $request, string $dataset): RedirectResponse
    {
        abort_unless(isset(self::IMPORTS[$dataset]), 404);
        abort_unless($request->user()->can(self::IMPORTS[$dataset][1]), 403);
        $request->validate(['file' => ['required', 'file', 'max:4096', $dataset === 'reglages' ? 'mimetypes:application/json,text/plain' : 'mimes:csv,txt']]);
        $path = $request->file('file')->getRealPath();

        $result = match ($dataset) {
            'zones' => $this->importZones($path),
            'services' => $this->importServices($path),
            'categories-pieces' => $this->importPartCategories($path),
            'catalogue' => $this->importCatalogue($request, $path),
            'reglages' => $this->importSettings($path),
        };
        ActivityLog::record('data.imported', null, ['jeu' => $dataset, 'résultat' => $result['message']]);

        return back()->with('success', self::IMPORTS[$dataset][0].' : '.$result['message'])->with('importErrors', $result['errors'] ?? []);
    }

    private function importZones(string $path): array
    {
        $cities = 0;
        $hoods = 0;
        $errors = [];
        foreach (Csv::read($path) as $i => $row) {
            $name = $row['ville'] ?? '';
            if ($name === '') {
                $errors[] = 'Ligne '.($i + 2).' : ville manquante.';

                continue;
            }
            $lat = str_replace(',', '.', (string) ($row['latitude'] ?? ''));
            $lng = str_replace(',', '.', (string) ($row['longitude'] ?? ''));
            if (! is_numeric($lat) || ! is_numeric($lng) || $lat < 9 || $lat > 15.5 || $lng < -6 || $lng > 2.5) {
                $errors[] = 'Ligne '.($i + 2).' : coordonnées absentes ou hors du Burkina Faso.';

                continue;
            }
            $city = City::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'latitude' => (float) $lat, 'longitude' => (float) $lng, 'is_active' => true]);
            $cities += $city->wasRecentlyCreated ? 1 : 0;
            if (filled($row['quartier'] ?? null)) {
                $hood = Neighborhood::firstOrCreate(
                    ['city_id' => $city->id, 'name' => $row['quartier']],
                    ['latitude' => (float) $lat, 'longitude' => (float) $lng],
                );
                $hoods += $hood->wasRecentlyCreated ? 1 : 0;
            }
        }

        return ['message' => "$cities ville(s) et $hoods quartier(s) ajoutés (les existants sont conservés).", 'errors' => $errors];
    }

    private function importServices(string $path): array
    {
        $created = 0;
        foreach (Csv::read($path) as $row) {
            if (blank($row['categorie'] ?? null) || blank($row['service'] ?? null)) {
                continue;
            }
            $category = ServiceCategory::firstOrCreate(['slug' => Str::slug($row['categorie'])], ['name' => $row['categorie']]);
            $service = Service::firstOrCreate(['slug' => Str::slug($row['service'])], ['name' => $row['service'], 'service_category_id' => $category->id, 'is_active' => true]);
            $created += $service->wasRecentlyCreated ? 1 : 0;
        }

        return ['message' => "$created service(s) ajouté(s)."];
    }

    private function importPartCategories(string $path): array
    {
        $created = 0;
        foreach (Csv::read($path) as $row) {
            if (blank($row['nom'] ?? null)) {
                continue;
            }
            $category = PartCategory::firstOrCreate(['slug' => Str::slug($row['nom'])], ['name' => $row['nom'], 'icon' => $row['icone'] ?: '⚙️']);
            $created += $category->wasRecentlyCreated ? 1 : 0;
        }

        return ['message' => "$created catégorie(s) ajoutée(s)."];
    }

    private function importCatalogue(Request $request, string $path): array
    {
        $shop = Provider::findOrFail($request->validate(['provider_id' => ['required', 'exists:providers,id']])['provider_id']);
        abort_unless($shop->canSellParts(), 422, 'Ce prestataire ne peut pas vendre de pièces.');
        $result = (new CatalogImporter($shop))->import($path);

        return ['message' => "{$result['created']} pièce(s) ajoutée(s), {$result['updated']} mise(s) à jour pour {$shop->business_name}.", 'errors' => $result['errors']];
    }

    private function importSettings(string $path): array
    {
        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            return ['message' => 'fichier JSON invalide, rien n\'a été modifié.'];
        }
        // Uniquement les clés connues, sans les secrets, avec le bon type.
        $definitions = array_filter(SettingsRegistry::all(), fn ($d) => $d['type'] !== 'secret');
        $values = [];
        foreach (array_intersect_key($data, $definitions) as $key => $value) {
            $default = $definitions[$key]['default'];
            if (gettype($value) === gettype($default) || (is_numeric($value) && is_numeric($default))) {
                $values[$key] = $value;
            }
        }
        Setting::store($values);

        return ['message' => count($values).' réglage(s) importé(s).'];
    }
}
