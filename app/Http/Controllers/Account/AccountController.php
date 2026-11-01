<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Rules\BurkinaPhone;
use App\Support\Notifier;
use App\Support\Phone;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function show(Request $request): View
    {
        // Profil et mot de passe ; les véhicules, favoris et avis ont leurs pages dans l'espace client.
        $user = $request->user()->load(['city', 'neighborhood']);

        return view('account.show', [
            'user' => $user,
            'cities' => City::where('is_active', true)->orderBy('name')->with('neighborhoods:id,city_id,name')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->merge([
            'phone' => $request->filled('phone') ? (Phone::normalize($request->input('phone')) ?? $request->input('phone')) : null,
            'email' => $request->filled('email') ? Str::lower(trim($request->input('email'))) : null,
        ]);

        // Email obligatoire (identifiant) ; téléphone en second, obligatoire seulement si l'admin l'exige.
        $phoneRequired = $user->isClient() && feature('client_phone_required');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:120', Rule::unique('users')->ignore($user->id)],
            'phone' => [$phoneRequired ? 'required' : 'nullable', new BurkinaPhone, Rule::unique('users')->ignore($user->id)],
            'city_id' => ['nullable', 'exists:cities,id'],
            'neighborhood_id' => ['nullable', Rule::exists('neighborhoods', 'id')->where('city_id', $request->input('city_id'))],
        ]);

        $user->fill($data);
        $emailChanged = $user->isDirty('email');
        if ($emailChanged) {
            // Nouvelle adresse = nouvelle confirmation.
            $user->email_verified_at = null;
        }
        $user->save();

        if ($emailChanged) {
            return Notifier::send($user, new VerifyEmail)
                ? back()->with('success', 'Profil mis à jour. Confirmez votre nouvelle adresse email avec le lien envoyé à '.$user->email.'.')
                : back()->with('error', 'Profil mis à jour, mais le lien de confirmation n\'a pas pu être envoyé : l\'envoi d\'emails n\'est pas encore en service.');
        }

        return back()->with('success', 'Profil mis à jour.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();
        $user->update(['password' => $request->input('password')]);
        // Les autres appareils (navigateurs, application mobile) sont déconnectés.
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();

        return back()->with('success', 'Mot de passe modifié. Vos autres appareils ont été déconnectés.');
    }
}
