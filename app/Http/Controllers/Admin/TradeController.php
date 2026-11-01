<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProviderStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\City;
use App\Models\Plan;
use App\Models\ProfileField;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Support\Listing;
use App\Support\ProviderProfiles;
use App\Support\TradeModules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Espace d'un métier dans l'administration : ses fiches (avec les colonnes propres au métier),
 * ses modules, son formulaire et ses offres d'abonnement.
 */
class TradeController extends Controller
{
    public function show(Request $request, ProviderType $type): View
    {
        $kind = $type->profileKind();
        $profile = ProviderProfiles::get($kind);
        // Colonnes propres au métier : les questions posées à l'inscription.
        $columns = $profile ? array_slice(ProviderProfiles::copy($kind)['signup'], 0, 3) : [];

        $status = ProviderStatus::tryFrom((string) $request->query('status'));
        $q = trim((string) $request->query('q'));
        $like = Listing::like($q);

        $query = Provider::query()
            ->where('type', $type->slug)
            ->with(array_filter(['user:id,name,email,is_active', 'city:id,name', 'lock', $profile['relation'] ?? null]))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($request->query('city'), fn ($query, $city) => $query->where('city_id', $city))
            ->when(Listing::flag($request, 'night') !== null, fn ($query) => $query->where('night_service', Listing::flag($request, 'night')))
            ->when(Listing::flag($request, 'featured') !== null, fn ($query) => $query->where('is_featured', Listing::flag($request, 'featured')))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('business_name', 'ilike', $like)
                ->orWhereHas('user', fn ($u) => $u->where('email', 'ilike', $like))
                ->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $q).'%')));

        $providers = Listing::sort($query, $request, [
            'name' => 'providers.business_name',
            'city' => fn ($q, $dir) => $q->orderBy(City::select('name')->whereColumn('cities.id', 'providers.city_id'), $dir),
            'status' => 'providers.status',
            'rating' => 'providers.rating_avg',
            'created' => 'providers.created_at',
        ], 'created', 'desc')->paginate(25)->withQueryString();

        $all = Provider::where('type', $type->slug);

        return view('admin.trades.show', [
            'type' => $type,
            'kind' => $kind,
            'profile' => $profile,
            'columns' => $columns,
            'providers' => $providers,
            'cities' => City::orderBy('name')->get(['id', 'name']),
            'counts' => (clone $all)->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status'),
            'stats' => [
                'total' => (clone $all)->count(),
                'rating' => (clone $all)->where('reviews_count', '>', 0)->avg('rating_avg'),
                'subscribers' => (clone $all)->whereHas('subscriptions', fn ($s) => $s->where('status', 'active'))->count(),
                'new' => (clone $all)->where('created_at', '>=', now()->subDays(30))->count(),
            ],
            'modules' => TradeModules::all(),
            'fields' => ProfileField::where('provider_type', $type->slug)->ordered()->get(),
            'plans' => Plan::ordered()->get()->filter(fn (Plan $p) => empty($p->provider_types) || in_array($type->slug, $p->provider_types, true))->values(),
        ]);
    }

    public function modules(Request $request, ProviderType $type): RedirectResponse
    {
        $data = $request->validate([
            'modules' => ['nullable', 'array'],
            'modules.*' => [Rule::in(TradeModules::keys())],
        ]);

        $before = $type->modules ?? [];
        $type->update(['modules' => $data['modules'] ?? []]);
        $dropped = array_diff($data['modules'] ?? [], $type->modules);

        ActivityLog::record('provider_type.modules', $type, [
            'ajoutés' => implode(', ', array_map([TradeModules::class, 'label'], array_diff($type->modules, $before))) ?: '—',
            'retirés' => implode(', ', array_map([TradeModules::class, 'label'], array_diff($before, $type->modules))) ?: '—',
        ]);

        $message = 'Modules du métier « '.$type->name.' » enregistrés.';
        if ($dropped) {
            $message .= ' Non activés faute de prérequis : '.implode(', ', array_map([TradeModules::class, 'label'], $dropped)).'.';
        }

        return back()->with('success', $message);
    }
}
