<?php

namespace App\Http\Controllers\Pro;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\ServiceCategory;
use App\Rules\BurkinaPhone;
use App\Support\Phone;
use App\Support\ProviderProfiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $provider = $request->user()->provider->load(['services', 'photos']);

        return view('pro.profile', [
            'provider' => $provider,
            'types' => ProviderType::options(),
            'categories' => ServiceCategory::with(['services' => fn ($q) => $q->active()])->orderBy('position')->get(),
            'cities' => City::where('is_active', true)->orderBy('name')->with('neighborhoods:id,city_id,name,latitude,longitude')->get(),
            'selectedServices' => $provider->services->pluck('id')->all(),
            'paymentMethods' => (array) setting('payment_methods'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $provider = $request->user()->provider;

        $request->merge([
            'phone' => Phone::normalize($request->input('phone')) ?? $request->input('phone'),
            'whatsapp' => $request->filled('whatsapp') ? (Phone::normalize($request->input('whatsapp')) ?? $request->input('whatsapp')) : null,
        ]);

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:120'],
            // Un type désactivé par l'admin reste valable pour les fiches qui l'ont déjà.
            'type' => ['required', Rule::exists('provider_types', 'slug')->where(fn ($q) => $q->where('is_active', true)->orWhere('slug', $provider->getRawOriginal('type')))],
            'manager_name' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'phone' => ['required', new BurkinaPhone],
            'whatsapp' => ['nullable', new BurkinaPhone],
            'address' => ['nullable', 'string', 'max:200'],
            'city_id' => ['required', 'exists:cities,id'],
            'neighborhood_id' => ['nullable', Rule::exists('neighborhoods', 'id')->where('city_id', $request->input('city_id'))],
            // Limité grossièrement au Burkina Faso et ses abords.
            'latitude' => ['required', 'numeric', 'between:9,15.5'],
            'longitude' => ['required', 'numeric', 'between:-6,2.5'],
            'night_service' => ['boolean'],
            'is_formal' => ['boolean'],
            'rccm' => ['nullable', 'required_if:is_formal,1', 'string', 'max:40'],
            'ifu' => ['nullable', 'string', 'max:40'],
            'services' => ['array'],
            'services.*' => ['integer', 'exists:services,id'],
            'logo' => ['nullable', 'image', 'max:'.(int) setting('max_image_kb')],
            'delivery_details' => ['nullable', 'string', 'max:255'],
            'payment_methods' => ['array'],
            'payment_methods.*' => [Rule::in((array) setting('payment_methods'))],
        ] + ($provider->profileKind() ? ProviderProfiles::rules($provider->profileKind()) : []), [
            'latitude.required' => 'Placez votre établissement sur la carte.',
            'rccm.required_if' => 'Le numéro RCCM est requis pour une entreprise enregistrée.',
        ]);

        // Valeurs avant modification, pour repérer les changements sensibles d'une fiche validée.
        $original = collect(Provider::SENSITIVE_FIELDS)->mapWithKeys(fn ($f) => [$f => $provider->getRawOriginal($f)])->all();

        $provider->fill(collect($data)->except(['services', 'logo', 'details'])->all() + ['payment_methods' => []]);
        $provider->night_service = $request->boolean('night_service');
        $provider->is_formal = $request->boolean('is_formal');
        $provider->delivery_available = feature('delivery_enabled') && $request->boolean('delivery_available');

        if ($request->hasFile('logo')) {
            if ($provider->logo_path) {
                Storage::disk('public')->delete($provider->logo_path);
            }
            $provider->logo_path = $request->file('logo')->store('logos', 'public');
        }

        $typeChanged = $provider->isDirty('type');
        $provider->save();
        $provider->services()->sync($data['services'] ?? []);
        // Fiche métier : si le type vient de changer, le formulaire du nouveau métier s'affichera au prochain passage.
        if ($request->has('details') && ! $typeChanged) {
            $provider->saveDetails((array) $request->input('details'));
        }

        if ($provider->flagSensitiveChanges($original)) {
            return back()->with('success', feature('sensitive_changes_unpublish')
                ? 'Fiche enregistrée. Vos changements importants seront vérifiés avant que la fiche ne réapparaisse.'
                : 'Fiche enregistrée. Vos changements importants seront vérifiés par l\'équipe.');
        }

        return back()->with('success', 'Fiche enregistrée.');
    }
}
