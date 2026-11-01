<?php

namespace App\Http\Controllers;

use App\Enums\VehicleType;
use App\Models\AssistRequest;
use App\Models\City;
use App\Models\Neighborhood;
use App\Rules\BurkinaPhone;
use App\Support\AssistDispatch;
use App\Support\AssistProblems;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Dépannage en direct, côté conducteur : demande en quelques gestes, suivi et annulation.
 * Le lien de suivi (jeton aléatoire) suffit : pas besoin de compte.
 */
class AssistController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->user() && ! feature('assist_guests_allowed')) {
            return redirect()->guest(route('login'))->with('error', 'Connectez-vous pour demander un dépannage.');
        }

        // Une demande encore en cours : on la montre plutôt que d'en créer une autre.
        if ($current = $this->currentRequest($request)) {
            return redirect()->route('assist.show', $current);
        }

        return view('assist.create', [
            'problems' => AssistProblems::all(),
            'vehicles' => VehicleType::options(),
            'energies' => AssistProblems::energies(),
            'cities' => City::where('is_active', true)->orderBy('name')->with('neighborhoods:id,city_id,name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if(! $request->user() && ! feature('assist_guests_allowed'), 403);

        $request->merge(['contact_phone' => Phone::normalize((string) $request->input('contact_phone')) ?? $request->input('contact_phone')]);
        $data = $request->validate([
            'vehicle_type' => ['required', Rule::in(array_keys(VehicleType::options()))],
            'energy' => ['required', Rule::in(array_keys(AssistProblems::energies()))],
            'problem' => ['required', Rule::in(array_keys(AssistProblems::all()))],
            'description' => ['nullable', 'string', 'max:500'],
            // Position GPS (Burkina Faso) ou, à défaut, ville et quartier.
            'latitude' => ['nullable', 'required_without:city_id', 'numeric', 'between:9,15.5'],
            'longitude' => ['nullable', 'required_without:city_id', 'numeric', 'between:-6,2.6'],
            'city_id' => ['nullable', Rule::exists('cities', 'id')->where('is_active', true)],
            'neighborhood_id' => ['nullable', Rule::exists('neighborhoods', 'id')->where('city_id', $request->input('city_id'))],
            'landmark' => ['nullable', 'string', 'max:200'],
            'contact_name' => ['required', 'string', 'max:100'],
            'contact_phone' => ['required', new BurkinaPhone],
        ], [
            'latitude.required_without' => 'Partagez votre position ou choisissez votre ville.',
            'longitude.required_without' => 'Partagez votre position ou choisissez votre ville.',
        ]);

        if ($current = $this->currentRequest($request) ?? AssistRequest::active()->where('contact_phone', $data['contact_phone'])->first()) {
            return redirect()->route('assist.show', $current)->with('error', 'Vous avez déjà une demande en cours.');
        }

        if (! isset($data['latitude'])) {
            $place = isset($data['neighborhood_id']) ? Neighborhood::find($data['neighborhood_id']) : null;
            $city = City::find($data['city_id']);
            $data['latitude'] = $place?->latitude ?? $city->latitude;
            $data['longitude'] = $place?->longitude ?? $city->longitude;
        }

        $assist = AssistRequest::create(collect($data)->except('neighborhood_id')->all() + [
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);
        $request->session()->push('assist_tokens', $assist->token);

        $count = AssistDispatch::dispatch($assist);

        return redirect()->route('assist.show', $assist)->with($count ? 'success' : 'error', $count
            ? $count.' prestataire(s) proche(s) prévenu(s). Restez joignable au '.Phone::format($assist->contact_phone).'.'
            : 'Aucun prestataire disponible n\'a été trouvé près de vous. Appelez directement un dépanneur ci-dessous.');
    }

    public function show(AssistRequest $assist): View
    {
        return view('assist.show', ['assist' => $assist->load('provider'), 'state' => $this->state($assist)]);
    }

    /** État de la demande, interrogé régulièrement par la page de suivi. */
    public function status(AssistRequest $assist): JsonResponse
    {
        return response()->json($this->state($assist->load('provider')));
    }

    public function cancel(Request $request, AssistRequest $assist): RedirectResponse
    {
        abort_unless($this->owns($request, $assist), 403);
        if ($assist->isActive()) {
            AssistDispatch::close($assist, 'cancelled', 'Annulée par le conducteur.');
        }

        return redirect()->route('assist.show', $assist)->with('success', 'Demande annulée.');
    }

    private function state(AssistRequest $assist): array
    {
        $provider = in_array($assist->status, ['accepted', 'on_way', 'done'], true) ? $assist->provider : null;

        return [
            'status' => $assist->status,
            'label' => $assist->statusLabel(),
            'active' => $assist->isActive(),
            'provider' => $provider ? [
                'name' => $provider->business_name,
                'phone' => Phone::format($provider->phone),
                'tel' => $provider->phone,
                'url' => route('providers.show', $provider),
            ] : null,
        ];
    }

    private function owns(Request $request, AssistRequest $assist): bool
    {
        return in_array($assist->token, (array) $request->session()->get('assist_tokens', []), true)
            || ($assist->user_id && $assist->user_id === $request->user()?->id);
    }

    private function currentRequest(Request $request): ?AssistRequest
    {
        $tokens = (array) $request->session()->get('assist_tokens', []);
        if (! $tokens && ! $request->user()) {
            return null;
        }

        return AssistRequest::active()
            ->where(fn ($q) => $q->whereIn('token', $tokens)->when($request->user(), fn ($q, $u) => $q->orWhere('user_id', $u->id)))
            ->latest()->first();
    }
}
