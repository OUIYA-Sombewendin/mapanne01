<?php

namespace App\Http\Controllers\Pro;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PartCategory;
use App\Models\Provider;
use App\Models\StockCount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Inventaire physique : on compte un rayon, une catégorie ou toute la boutique,
 * on saisit les quantités réelles (à la main ou en scannant), puis l'écart corrige le stock.
 */
class StockCountController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $shop = $this->shop($request);

        return view('pro.manage.counts', [
            'provider' => $shop,
            'counts' => $shop->stockCounts()->with('user:id,name')->withCount(['lines', 'lines as counted_count' => fn ($q) => $q->whereNotNull('counted')])->paginate(20),
            'open' => $shop->stockCounts()->where('status', 'open')->first(),
            'categories' => PartCategory::tree(),
            'shelves' => $shop->products()->whereNotNull('shelf')->distinct()->orderBy('shelf')->pluck('shelf'),
            'total' => $shop->products()->count(),
        ]);
    }

    /** Démarre un comptage : le stock connu de chaque pièce concernée est mémorisé. */
    public function store(Request $request): RedirectResponse
    {
        $shop = $this->shop($request);
        if ($open = $shop->stockCounts()->where('status', 'open')->first()) {
            return redirect()->route('pro.manage.counts.show', $open)->with('error', 'Un inventaire est déjà en cours : terminez-le ou annulez-le d\'abord.');
        }
        $data = $request->validate([
            'scope' => ['required', Rule::in(['all', 'category', 'shelf'])],
            'category' => ['required_if:scope,category', 'nullable', 'exists:part_categories,id'],
            'shelf' => ['required_if:scope,shelf', 'nullable', 'string', 'max:60'],
        ], ['category.required_if' => 'Choisissez la catégorie à compter.', 'shelf.required_if' => 'Choisissez l\'emplacement à compter.']);

        $products = $shop->products()
            ->when($data['scope'] === 'category', fn ($q) => $q->whereIn('part_category_id', PartCategory::familyIdsOf($data['category'])))
            ->when($data['scope'] === 'shelf', fn ($q) => $q->where('shelf', $data['shelf']))
            ->get(['id', 'stock']);
        if ($products->isEmpty()) {
            return back()->with('error', 'Aucune pièce à compter avec ce choix.');
        }

        $label = match ($data['scope']) {
            'category' => 'Catégorie : '.PartCategory::find($data['category'])->name,
            'shelf' => 'Emplacement : '.$data['shelf'],
            default => 'Toute la boutique',
        };
        $count = $shop->stockCounts()->create(['scope' => $label, 'user_id' => $request->user()->id]);
        $count->lines()->createMany($products->map(fn ($p) => ['product_id' => $p->id, 'expected' => $p->stock])->all());

        return redirect()->route('pro.manage.counts.show', $count);
    }

    public function show(Request $request, StockCount $count): View
    {
        $this->owned($request, $count);
        $lines = $count->lines()
            ->with('product:id,name,reference,barcode,shelf,stock,cost_price,unit')
            ->get()
            ->sortBy(fn ($l) => [$l->product->shelf ?? '~', $l->product->name])
            ->values();

        $counted = $lines->whereNotNull('counted');

        return view('pro.manage.count-show', [
            'provider' => $count->provider,
            'count' => $count,
            'lines' => $lines,
            'summary' => [
                'total' => $lines->count(),
                'counted' => $counted->count(),
                'gaps' => $counted->filter(fn ($l) => $l->counted !== $l->product->stock)->count(),
                'missing' => $counted->sum(fn ($l) => max(0, $l->product->stock - $l->counted)),
                'value' => $counted->sum(fn ($l) => ($l->counted - $l->product->stock) * (int) $l->product->cost_price),
                // Ventes ou entrées enregistrées depuis le début du comptage.
                'moved' => $lines->filter(fn ($l) => $l->product->stock !== $l->expected)->count(),
            ],
        ]);
    }

    /** Enregistre les quantités saisies (on peut revenir plus tard). */
    public function update(Request $request, StockCount $count): RedirectResponse
    {
        $this->owned($request, $count, open: true);
        $data = $request->validate([
            'counted' => ['array'],
            'counted.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], ['counted.*.min' => 'Une quantité comptée ne peut pas être négative.']);

        $lines = $count->lines()->whereIn('id', array_keys($data['counted'] ?? []))->get()->keyBy('id');
        foreach ($data['counted'] ?? [] as $id => $value) {
            if ($line = $lines->get($id)) {
                $line->update(['counted' => $value === null || $value === '' ? null : (int) $value]);
            }
        }
        $count->touch();

        return back()->with('success', 'Comptage enregistré.');
    }

    public function apply(Request $request, StockCount $count): RedirectResponse
    {
        $this->owned($request, $count, open: true);
        if (! $count->lines()->whereNotNull('counted')->exists()) {
            return back()->with('error', 'Aucune quantité saisie : comptez au moins une pièce.');
        }
        $adjusted = $count->apply();
        ActivityLog::record('stock_count.applied', $count->provider, ['inventaire' => $count->id, 'corrections' => $adjusted]);

        return redirect()->route('pro.manage.counts.show', $count)->with('success', $adjusted
            ? "Inventaire validé : {$adjusted} pièce(s) corrigée(s)."
            : 'Inventaire validé : aucun écart, votre stock était juste.');
    }

    public function destroy(Request $request, StockCount $count): RedirectResponse
    {
        $this->owned($request, $count, open: true);
        $count->forceFill(['status' => 'cancelled'])->save();

        return redirect()->route('pro.manage.counts')->with('success', 'Inventaire annulé : le stock n\'a pas été modifié.');
    }

    private function owned(Request $request, StockCount $count, bool $open = false): void
    {
        abort_unless($count->provider_id === $this->shop($request)->id, 404);
        abort_if($open && ! $count->isOpen(), 422, 'Cet inventaire est terminé.');
    }

    private function shop(Request $request): Provider
    {
        $shop = $request->user()->provider;
        abort_unless($shop->canSellParts(), 403, 'La gestion du stock est réservée aux vendeurs de pièces.');

        return $shop;
    }
}
