<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Neighborhood;
use App\Models\User;
use App\Notifications\NewProviderRegistered;
use App\Rules\BurkinaPhone;
use App\Support\Notifier;
use App\Support\Phone;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(Request $request): View
    {
        $clients = feature('registration_clients_enabled');
        $pros = feature('registration_providers_enabled');
        abort_unless($clients || $pros, 403, 'Les inscriptions sont momentanément fermées.');

        $wanted = $request->query('type') === 'pro' ? 'pro' : 'client';

        return view('auth.register', [
            'accountType' => ($wanted === 'pro' && $pros) || ! $clients ? 'pro' : 'client',
            'clientsOpen' => $clients,
            'prosOpen' => $pros,
            'cities' => City::where('is_active', true)->orderBy('name')->with('neighborhoods:id,city_id,name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = static::register($request);

        Auth::login($user);
        $request->session()->regenerate();

        $message = $user->isProvider()
            ? 'Bienvenue ! Votre fiche est en attente de validation. Complétez-la pour accélérer la vérification.'
            : 'Bienvenue sur MaPanne !';
        $message .= ' Un email de confirmation vous a été envoyé.';

        return redirect($user->homeRoute())->with('success', $message);
    }

    /** Inscription partagée par le web et l'API. */
    public static function register(Request $request): User
    {
        $request->merge([
            'phone' => $request->filled('phone') ? (Phone::normalize($request->input('phone')) ?? $request->input('phone')) : null,
            'email' => $request->filled('email') ? Str::lower(trim($request->input('email'))) : null,
        ]);

        $isPro = $request->input('account_type') === 'pro';

        // Les inscriptions peuvent être fermées par type depuis l'administration.
        if (! feature($isPro ? 'registration_providers_enabled' : 'registration_clients_enabled')) {
            throw ValidationException::withMessages(['account_type' => 'Ce type d\'inscription est momentanément fermé.']);
        }

        // Email = identifiant principal ; téléphone en second (obligatoire pour les pros : les clients les appellent).
        $phoneRequired = $isPro || feature('client_phone_required');

        $data = $request->validate([
            'account_type' => ['required', Rule::in(['client', 'pro'])],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'phone' => [$phoneRequired ? 'required' : 'nullable', new BurkinaPhone, 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'city_id' => [$isPro ? 'required' : 'nullable', 'exists:cities,id'],
            'neighborhood_id' => ['nullable', Rule::exists('neighborhoods', 'id')->where('city_id', $request->input('city_id'))],
            'business_name' => [Rule::requiredIf($isPro), 'nullable', 'string', 'max:120'],
            'provider_type' => [Rule::requiredIf($isPro), 'nullable', Rule::exists('provider_types', 'slug')->where('is_active', true)],
            'terms' => ['accepted'],
        ], [
            'email.unique' => 'Cet email est déjà utilisé. Connectez-vous plutôt.',
            'phone.unique' => 'Ce numéro est déjà utilisé. Connectez-vous plutôt.',
            'terms.accepted' => 'Vous devez accepter les conditions d\'utilisation.',
        ]);

        $user = DB::transaction(function () use ($data, $isPro) {
            $user = new User([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'],
                'password' => $data['password'],
                'city_id' => $data['city_id'] ?? null,
                'neighborhood_id' => $data['neighborhood_id'] ?? null,
            ]);
            $user->role = $isPro ? UserRole::Provider : UserRole::Client;
            $user->save();

            if ($isPro) {
                $neighborhood = isset($data['neighborhood_id']) ? Neighborhood::find($data['neighborhood_id']) : null;
                $city = City::find($data['city_id']);

                // Statut « pending » imposé par le modèle : invisible jusqu'à validation admin.
                $user->provider()->create([
                    'type' => $data['provider_type'],
                    'business_name' => $data['business_name'],
                    'manager_name' => $data['name'],
                    'phone' => $data['phone'],
                    'whatsapp' => $data['phone'],
                    'city_id' => $city->id,
                    'neighborhood_id' => $neighborhood?->id,
                    // Position provisoire = centre du quartier/ville, à affiner sur la carte.
                    'latitude' => $neighborhood?->latitude ?? $city->latitude,
                    'longitude' => $neighborhood?->longitude ?? $city->longitude,
                ]);
            }

            return $user;
        });

        // Les envois d'emails ne doivent jamais bloquer l'inscription (Notifier journalise les échecs).
        Notifier::send($user, new VerifyEmail);
        if ($user->isProvider() && filled(setting('admin_notification_email'))) {
            Notifier::sendTo(setting('admin_notification_email'), new NewProviderRegistered($user->provider), 'notify_admin_new_provider');
        }

        return $user;
    }
}
