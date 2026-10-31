<?php

namespace App\Http\Controllers\Pro;

use App\Enums\VehicleType;
use App\Http\Controllers\Controller;
use App\Models\PartCategory;
use App\Models\Product;
use App\Models\ProductPhoto;
use App\Models\Provider;
use App\Support\CatalogImporter;
use App\Support\Inventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Catalogue de pièces du vendeur (types de prestataires autorisés à vendre).
 */
class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $shop = $this->shop($request);
        $q = trim((string) $request->query('q'));

        $products = $shop->products()
            ->with(['photos', 'category:id,name'])
            ->when($q !== '', fn ($query) => $query->where('name', 'ilike', '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%'))
            ->when($request->query('filter') === 'out', fn ($query) => $query->where('stock', 0)->where('on_order', false))
            ->when($request->query('filter') === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($request->query('filter') === 'pending', fn ($query) => $query->whereNull('approved_at'))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('pro.products.index', [
            'provider' => $shop,
            'products' => $products,
            'stats' => [
                'total' => $shop->products()->count(),
                'out' => $shop->products()->where('stock', 0)->where('on_order', false)->count(),
                'views' => (int) $shop->products()->sum('views_count'),
                'contacts' => (int) $shop->products()->sum('contacts_count'),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form($this->shop($request), new Product(['vehicle_types' => ['voiture'], 'condition' => 'new', 'is_active' => true]));
    }

    public function store(Request $request): RedirectResponse
    {
        $shop = $this->shop($request);
        $limit = $shop->planLimit('max_products');
        if ($limit !== null && $shop->products()->count() >= $limit) {
            return redirect()->route(feature('subscriptions_enabled') ? 'pro.subscription' : 'pro.products.index')
                ->with('error', "Votre offre permet {$limit} produits au maximum. Passez à une offre supérieure pour en ajouter.");
        }
        $data = $this->validated($request);

        $product = DB::transaction(function () use ($shop, $data, $request) {
            // Le stock de départ est enregistré comme un mouvement « Stock initial ».
            $product = $shop->products()->create(['stock' => 0] + $data);
            if ($data['stock'] > 0) {
                Inventory::move($product, 'in', $data['stock'], 'initial', $data['cost_price'] ?? null);
            }
            $this->storePhotos($request, $product);

            return $product;
        });

        return redirect()->route('pro.products.index')->with('success', $product->isPendingApproval()
            ? "« {$product->name} » ajouté : il sera visible après vérification par l'équipe."
            : "« {$product->name} » ajouté au catalogue.");
    }

    public function edit(Request $request, Product $product): View
    {
        return $this->form($this->shop($request, $product), $product->load('photos'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->shop($request, $product);
        $data = $this->validated($request, $product);
        $stock = $data['stock'];
        $product->update(collect($data)->except('stock')->all());
        if ($stock !== $product->stock) {
            Inventory::move($product, 'adjust', $stock, 'inventory', null, 'Modification de la fiche produit');
        }
        $this->storePhotos($request, $product);

        return redirect()->route('pro.products.index')->with('success', 'Produit mis à jour.');
    }

    /** Mise à jour rapide du stock et du prix depuis la liste (sans ouvrir la fiche). */
    public function quickUpdate(Request $request, Product $product): RedirectResponse
    {
        $this->shop($request, $product);

        $data = $request->validate([
            'stock' => ['required', 'integer', 'min:0', 'max:100000'],
            'price' => ['required', 'integer', 'min:50', 'max:50000000'],
        ]);
        $product->update(['price' => $data['price']]);
        if ((int) $data['stock'] !== $product->stock) {
            Inventory::move($product, 'adjust', (int) $data['stock'], 'quick');
        }

        return back()->with('success', 'Stock et prix mis à jour.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->shop($request, $product);

        foreach ($product->photos as $photo) {
            Storage::disk('public')->delete($photo->path);
        }
        $product->delete();

        return redirect()->route('pro.products.index')->with('success', 'Produit supprimé.');
    }

    public function destroyPhoto(Request $request, ProductPhoto $photo): RedirectResponse
    {
        $this->shop($request, $photo->product);

        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        return back()->with('success', 'Photo supprimée.');
    }

    // Import par fichier ------------------------------------------------------------

    public function importForm(Request $request): View
    {
        return view('pro.products.import', [
            'provider' => $this->shop($request),
            'categories' => PartCategory::orderBy('position')->get(),
            'columns' => CatalogImporter::COLUMNS,
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $shop = $this->shop($request);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']], [
            'file.mimes' => 'Le fichier doit être au format CSV (dans Excel : Fichier › Enregistrer sous › CSV).',
        ]);

        $result = (new CatalogImporter($shop))->import($request->file('file')->getRealPath());

        return redirect()->route('pro.products.import')
            ->with('success', "{$result['created']} pièce(s) ajoutée(s), {$result['updated']} mise(s) à jour.")
            ->with('importErrors', $result['errors']);
    }

    public function template(Request $request): StreamedResponse
    {
        $this->shop($request);

        return response()->streamDownload(function () {
            echo "\u{FEFF}".implode(';', array_keys(CatalogImporter::COLUMNS))."\n";
            echo "Plaquettes de frein avant;freinage;18000;6;Bosch;0986494;Toyota Corolla 2008-2014;voiture;neuf;20000;6 mois;Plaquettes céramique\n";
            echo "Batterie moto 12V 5Ah;electricite-et-batterie;12000;10;Yuasa;;Yamaha Crypton;moto,tricycle;neuf;;;\n";
        }, 'modele-catalogue-mapanne.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------------

    /** Boutique de l'utilisateur ; vérifie le droit de vendre et la propriété du produit. */
    private function shop(Request $request, ?Product $product = null): Provider
    {
        $shop = $request->user()->provider;

        abort_unless($shop->canSellParts(), 403, 'Le catalogue de pièces n\'est pas ouvert à votre type d\'activité.');
        abort_if($product && $product->provider_id !== $shop->id, 403);

        return $shop;
    }

    private function maxPhotos(): int
    {
        return max(1, (int) setting('product_max_photos'));
    }

    private function conditions(): array
    {
        return feature('allow_used_parts') ? Product::CONDITIONS : ['new' => Product::CONDITIONS['new']];
    }

    private function form(Provider $shop, Product $product): View
    {
        return view('pro.products.form', [
            'provider' => $shop,
            'product' => $product,
            'categories' => PartCategory::orderBy('position')->get(),
            'vehicleTypes' => VehicleType::options(),
            'conditions' => $this->conditions(),
            'maxPhotos' => $this->maxPhotos(),
        ]);
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $existingPhotos = $product?->photos()->count() ?? 0;
        $max = $this->maxPhotos();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'part_category_id' => ['required', 'exists:part_categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'brand' => ['nullable', 'string', 'max:60'],
            'reference' => ['nullable', 'string', 'max:60'],
            'compatibility' => ['nullable', 'string', 'max:255'],
            'vehicle_types' => ['required', 'array', 'min:1'],
            'vehicle_types.*' => [Rule::enum(VehicleType::class)],
            'condition' => ['required', Rule::in(array_keys($this->conditions()))],
            'warranty' => ['nullable', 'string', 'max:60'],
            'price' => ['required', 'integer', 'min:50', 'max:50000000'],
            'compare_at_price' => ['nullable', 'integer', 'gt:price', 'max:50000000'],
            'stock' => ['required', 'integer', 'min:0', 'max:100000'],
            'cost_price' => ['nullable', 'integer', 'min:0', 'max:50000000'],
            'stock_alert' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'shelf' => ['nullable', 'string', 'max:60'],
            'on_order' => ['boolean'],
            'is_active' => ['boolean'],
            'photos' => ['array', 'max:'.max(0, $max - $existingPhotos)],
            'photos.*' => ['image', 'max:'.(int) setting('max_image_kb')],
        ], [
            'vehicle_types.required' => 'Choisissez au moins un type de véhicule compatible.',
            'photos.max' => 'Maximum '.$max.' photos par produit.',
            'compare_at_price.gt' => 'Le prix barré doit être supérieur au prix de vente.',
        ]);

        $data['vehicle_types'] = array_values(array_unique($data['vehicle_types']));
        $data['on_order'] = $request->boolean('on_order');
        $data['is_active'] = $request->boolean('is_active');
        $data['compare_at_price'] ??= null;
        $data['cost_price'] ??= null;
        $data['stock_alert'] ??= null;
        $data['shelf'] ??= null;
        $data['stock'] = (int) $data['stock'];

        return collect($data)->except('photos')->all();
    }

    private function storePhotos(Request $request, Product $product): void
    {
        $position = (int) $product->photos()->max('position');

        foreach ($request->file('photos', []) as $file) {
            $product->photos()->create(['path' => $file->store('products', 'public'), 'position' => ++$position]);
        }
    }
}
