<?php

namespace App\Http\Controllers\Pro;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\City;
use App\Models\Neighborhood;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\User;
use App\Notifications\NewProviderRegistered;
use App\Rules\BurkinaPhone;
use App\Support\Notifier;
use App\Support\Phone;
use App\Support\ShopTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Commerçant multi-boutiques : liste des boutiques du compte, ajout d'une boutique
 * (validée par l'admin comme toute fiche) et choix de la boutique active.
 */
class ShopController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('pro.shops', [
            'shops' => $user->shops()->load(['city:id,name', 'neighborhood:id,name'])->each(fn (Provider $s) => $s->setAttribute('my_role', $user->shopRole($s))),
            'current' => $user->provider,
            'canAdd' => $this->canAdd($user),
            'max' => $this->maxShops($user),
            'owned' => $user->providers()->count(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->canAdd($request->user())) {
            return redirect()->route('pro.shops.index')->with('error', 'Vous avez atteint le nombre maximum de boutiques ('.$this->maxShops($request->user()).').');
        }

        return view('pro.shop-create', [
            'types' => ProviderType::active()->where('can_sell_parts', true)->values(),
            'cities' => City::where('is_active', true)->orderBy('name')->with('neighborhoods:id,city_id,name,latitude,longitude')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canAdd($user), 422, 'Nombre maximum de boutiques atteint.');

        $request->merge(['phone' => Phone::normalize($request->input('phone')) ?? $request->input('phone')]);
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(ProviderType::active()->where('can_sell_parts', true)->pluck('slug')->all())],
            'phone' => ['required', new BurkinaPhone],
            'city_id' => ['required', Rule::exists('cities', 'id')->where('is_active', true)],
            'neighborhood_id' => ['nullable', Rule::exists('neighborhoods', 'id')->where('city_id', $request->input('city_id'))],
            'address' => ['nullable', 'string', 'max:200'],
        ], ['type.in' => 'Choisissez un type de boutique de pièces.']);

        // Position provisoire = centre du quartier ou de la ville, à préciser ensuite sur la carte de la fiche.
        $place = ! empty($data['neighborhood_id']) ? Neighborhood::find($data['neighborhood_id']) : City::find($data['city_id']);
        $shop = $user->providers()->create($data + [
            'manager_name' => $user->name,
            'whatsapp' => $data['phone'],
            'latitude' => $place->latitude,
            'longitude' => $place->longitude,
        ]);
        $user->forgetShops();
        $request->session()->put(User::CURRENT_SHOP, $shop->id);

        ActivityLog::record('provider.shop_added', $shop);
        if (filled(setting('admin_notification_email'))) {
            Notifier::sendTo(setting('admin_notification_email'), new NewProviderRegistered($shop), 'notify_admin_new_provider');
        }

        return redirect()->route('pro.profile')->with('success', "Boutique « {$shop->business_name} » déclarée. Placez-la sur la carte et complétez sa fiche : elle sera visible après validation par l'équipe.");
    }

    /** Change la boutique sur laquelle on travaille (stock, ventes, fiche…). */
    public function switch(Request $request, Provider $provider): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->shops()->contains('id', $provider->id), 403);
        $request->session()->put(User::CURRENT_SHOP, $provider->id);

        return redirect()->route('pro.dashboard')->with('success', "Vous travaillez maintenant sur « {$provider->business_name} » (".ShopTeam::label($user->shopRole($provider)).').');
    }

    private function maxShops(User $user): int
    {
        $planLimit = $user->providers()->first()?->planLimit('max_shops');

        return $planLimit ?? max(1, (int) setting('max_shops_per_owner'));
    }

    /** Seuls les commerçants (au moins une boutique pouvant vendre des pièces) ajoutent des boutiques. */
    private function canAdd(User $user): bool
    {
        return feature('multi_shop_enabled')
            && $user->providers()->whereIn('type', ProviderType::sellingSlugs())->exists()
            && $user->providers()->count() < $this->maxShops($user);
    }
}
