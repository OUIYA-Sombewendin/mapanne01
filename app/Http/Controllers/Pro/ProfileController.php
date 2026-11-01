<?php

namespace App\Http\Controllers\Pro;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\ProfileField;
use App\Models\Provider;
use App\Models\Service;
use App\Notifications\SimpleMessage;
use App\Rules\BurkinaPhone;
use App\Support\ImageOptimizer;
use App\Support\Notifier;
use App\Support\Phone;
use App\Support\ProviderProfiles;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Fiche du prestataire. Chaque métier a son propre formulaire (resources/views/pro/profile/<métier>.blade.php),
 * ses propres sections et ses propres services. Le type d'activité n'est pas modifiable ici :
 * changer de métier passe par le support (ou par une nouvelle boutique pour un commerçant).
 */
class ProfileController extends Controller
{
    public const VIEWS = ['mechanic', 'garage', 'tow', 'station', 'shop'];

    public function edit(Request $request): View
    {
        $provider = $request->user()->provider->load(['services', 'photos']);
        $kind = $provider->profileKind();

        return view('pro.profile.'.(in_array($kind, self::VIEWS, true) ? $kind : 'generic'), [
            'provider' => $provider,
            'copy' => ProviderProfiles::copy($kind),
            'completion' => $provider->profileCompletion(),
            'categories' => $this->categories($kind),
            'cities' => City::where('is_active', true)->orderBy('name')->with('neighborhoods:id,city_id,name,latitude,longitude')->get(),
            'selectedServices' => $provider->services->pluck('id')->all(),
            'paymentMethods' => (array) setting('payment_methods'),
            'productsCount' => $provider->canSellParts() ? $provider->products()->count() : 0,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $provider = $request->user()->provider;
        $kind = $provider->profileKind();
        $customFields = ProfileField::forProvider($provider);
        $allowedServices = Service::whereIn('service_category_id', $this->categories($kind)->pluck('id'))->pluck('id')->all();

        $request->merge([
            'phone' => Phone::normalize($request->input('phone')) ?? $request->input('phone'),
            'whatsapp' => $request->filled('whatsapp') ? (Phone::normalize($request->input('whatsapp')) ?? $request->input('whatsapp')) : null,
        ]);

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:120'],
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
            // Uniquement les services du métier : un garage ne peut pas cocher « Super 91 ».
            'services' => ['array'],
            'services.*' => ['integer', Rule::in($allowedServices)],
            'logo' => ['nullable', 'image', 'max:'.(int) setting('max_image_kb')],
            'delivery_details' => ['nullable', 'string', 'max:255'],
            'payment_methods' => ['array'],
            'payment_methods.*' => [Rule::in((array) setting('payment_methods'))],
        ] + ($kind ? ProviderProfiles::rules($kind) : []) + ProfileField::rulesFor($customFields), [
            'latitude.required' => 'Placez votre établissement sur la carte.',
            'rccm.required_if' => 'Le numéro RCCM est requis pour une entreprise enregistrée.',
            'services.*.in' => 'Ce service ne correspond pas à votre métier.',
        ]);

        // Fiche validée : les champs vérifiés (nom, téléphone, position, RCCM) ne changent plus directement,
        // ils deviennent une demande que l'équipe applique ou refuse.
        $requested = [];
        if ($provider->locksFields()) {
            foreach (Provider::LOCKED_FIELDS as $field) {
                if (array_key_exists($field, $data)) {
                    $requested[$field] = $data[$field];
                    unset($data[$field]);
                }
            }
        }

        // Valeurs avant modification, pour repérer les changements sensibles d'une fiche validée.
        $original = collect(Provider::SENSITIVE_FIELDS)->mapWithKeys(fn ($f) => [$f => $provider->getRawOriginal($f)])->all();

        $provider->fill(collect($data)->except(['services', 'logo', 'details', 'custom'])->all() + ['payment_methods' => []]);
        $provider->night_service = $request->boolean('night_service');
        $provider->is_formal = $request->boolean('is_formal');
        $provider->delivery_available = feature('delivery_enabled') && $provider->canSellParts() && $request->boolean('delivery_available');
        // Champs ajoutés par l'admin : on garde les réponses des champs désactivés ou d'un autre type.
        $provider->custom_fields = array_merge((array) $provider->custom_fields, $customFields->mapWithKeys(fn ($f) => [$f->key => $f->cast($request->input('custom.'.$f->key))])->all());

        if ($request->hasFile('logo')) {
            if ($provider->logo_path) {
                Storage::disk('public')->delete($provider->logo_path);
            }
            $provider->logo_path = ImageOptimizer::store($request->file('logo'), 'logos', 512);
        }

        $provider->save();
        if ($provider->hasModule('services')) {
            $provider->services()->sync($data['services'] ?? []);
        }
        if ($kind && $request->has('details.'.$kind)) {
            $provider->saveDetails((array) $request->input('details.'.$kind));
        }

        if ($requested && $changes = $provider->requestChanges($requested)) {
            $labels = collect(array_keys($changes))->map(fn ($f) => Provider::LOCKED_LABELS[$f] ?? $f)->unique()->implode(', ');
            Notifier::sendTo(setting('admin_notification_email'), new SimpleMessage(
                'Demande de modification : '.$provider->business_name,
                ['Le prestataire demande à modifier : '.$labels.'.', 'À vérifier dans l\'administration, fiche « '.$provider->business_name.' ».'],
            ));

            return back()->with('success', 'Fiche enregistrée. Votre demande de modification ('.$labels.') a été transmise à l\'équipe : elle sera appliquée après vérification.');
        }

        if ($provider->flagSensitiveChanges($original)) {
            return back()->with('success', feature('sensitive_changes_unpublish')
                ? 'Fiche enregistrée. Vos changements importants seront vérifiés avant que la fiche ne réapparaisse.'
                : 'Fiche enregistrée. Vos changements importants seront vérifiés par l\'équipe.');
        }

        return back()->with('success', 'Fiche enregistrée.');
    }

    private function categories(?string $kind): Collection
    {
        return ProviderProfiles::serviceCategories($kind);
    }
}
