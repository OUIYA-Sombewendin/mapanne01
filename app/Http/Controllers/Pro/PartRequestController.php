<?php

namespace App\Http\Controllers\Pro;

use App\Http\Controllers\Controller;
use App\Models\PartRequest;
use App\Models\Product;
use App\Notifications\SimpleMessage;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Côté vendeur : demandes de pièces ouvertes dans sa ville, auxquelles il répond par une offre.
 * Réservé aux boutiques validées (une boutique en attente ne peut pas démarcher les clients).
 */
class PartRequestController extends Controller
{
    public function index(Request $request): View
    {
        $shop = $this->shop($request);

        $requests = PartRequest::open()
            ->where(fn ($q) => $q->where('city_id', $shop->city_id)->orWhereNull('city_id'))
            ->where('user_id', '!=', $request->user()->id)
            ->with(['category:id,name,icon', 'city:id,name', 'offers' => fn ($q) => $q->where('provider_id', $shop->id)])
            ->withCount('offers')
            ->latest()
            ->paginate(20);

        return view('pro.part-requests', [
            'provider' => $shop,
            'requests' => $requests,
            'products' => $shop->products()->visible()->orderBy('name')->get(['products.id', 'products.name', 'products.price']),
        ]);
    }

    public function offer(Request $request, PartRequest $partRequest): RedirectResponse
    {
        $shop = $this->shop($request);
        abort_unless($partRequest->isOpen() && ($partRequest->city_id === null || $partRequest->city_id === $shop->city_id), 404);

        $data = $request->validate([
            'price' => ['required', 'integer', 'min:50', 'max:50000000'],
            'condition' => ['required', Rule::in(array_keys(feature('allow_used_parts') ? Product::CONDITIONS : ['new' => 1]))],
            'message' => ['nullable', 'string', 'max:500'],
            'product_id' => ['nullable', Rule::exists('products', 'id')->where('provider_id', $shop->id)],
        ]);

        $offer = $partRequest->offers()->where('provider_id', $shop->id)->first()
            ?? $partRequest->offers()->make()->forceFill(['provider_id' => $shop->id]);
        $isNew = ! $offer->exists;
        $offer->fill($data)->save();

        if ($isNew) {
            Notifier::send($partRequest->user, new SimpleMessage(
                'Nouvelle offre pour « '.$partRequest->title.' »',
                ["{$shop->business_name} vous propose cette pièce à {$offer->priceLabel()}.", $offer->message ?: ''],
                'Voir les offres', route('part-requests.show', $partRequest),
            ), 'notify_client_new_offer');
        }

        return back()->with('success', $isNew ? 'Offre envoyée au client.' : 'Offre mise à jour.');
    }

    private function shop(Request $request)
    {
        $shop = $request->user()->provider;
        abort_unless($shop->canSellParts() && $shop->isVisible(), 403, 'Les demandes de pièces sont réservées aux boutiques validées.');

        return $shop;
    }
}
