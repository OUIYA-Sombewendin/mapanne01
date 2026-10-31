<?php

namespace App\Http\Controllers\Pro;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Provider;
use App\Models\User;
use App\Rules\BurkinaPhone;
use App\Support\Phone;
use App\Support\ShopTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Throwable;

/**
 * Équipe de la boutique active : le propriétaire ajoute des employés (gérant, vendeur, caissier).
 * Un employé a son propre compte et ne voit que les zones permises par son rôle.
 */
class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $shop = $request->user()->provider;

        return view('pro.team', [
            'provider' => $shop,
            'members' => $shop->members()->orderBy('name')->get(),
            'roles' => ShopTeam::ROLES,
            'max' => $this->maxMembers($shop),
            'areas' => ShopTeam::AREAS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $shop = $request->user()->provider;
        if ($shop->members()->count() >= $this->maxMembers($shop)) {
            return back()->with('error', 'Nombre maximum d\'employés atteint ('.$this->maxMembers($shop).').');
        }

        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'phone' => $request->filled('phone') ? (Phone::normalize($request->input('phone')) ?? $request->input('phone')) : null,
        ]);
        $existing = User::where('email', $request->input('email'))->first();

        $data = $request->validate([
            'name' => [$existing ? 'nullable' : 'required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:120'],
            'phone' => ['nullable', new BurkinaPhone, Rule::unique('users', 'phone')->ignore($existing)],
            'role' => ['required', Rule::in(array_keys(ShopTeam::ROLES))],
            'password' => ['nullable', PasswordRule::min(8)],
        ]);

        if ($existing) {
            // Seul un compte employé existant (sans boutique à lui) peut rejoindre une autre boutique.
            if (! $existing->isProvider() || $existing->providers()->exists() || $existing->id === $request->user()->id) {
                return back()->withInput()->withErrors(['email' => 'Cette adresse appartient à un compte client, admin ou commerçant. Utilisez une autre adresse pour l\'employé.']);
            }
            if ($shop->members()->whereKey($existing->id)->exists()) {
                return back()->withErrors(['email' => 'Cette personne fait déjà partie de l\'équipe.']);
            }
            $member = $existing;
        } else {
            $member = DB::transaction(function () use ($data) {
                $member = new User([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'password' => $data['password'] ?? Str::password(24),
                ]);
                $member->role = UserRole::Provider;
                $member->save();

                return $member;
            });
        }

        $shop->members()->attach($member->id, ['role' => $data['role'], 'added_by' => $request->user()->id]);
        ActivityLog::record('shop.member_added', $shop, ['employé' => $member->email, 'rôle' => ShopTeam::label($data['role'])]);

        // Sans mot de passe provisoire : l'employé reçoit un lien pour choisir le sien.
        $message = "{$member->name} a rejoint l'équipe comme ".ShopTeam::label($data['role']).'.';
        if (! $existing && empty($data['password'])) {
            try {
                Password::broker()->sendResetLink(['email' => $member->email]);
                $message .= ' Un email lui a été envoyé pour choisir son mot de passe.';
            } catch (Throwable $e) {
                report($e);
                $message .= ' L\'email n\'a pas pu partir : donnez-lui un mot de passe provisoire.';
            }
        } elseif (! $existing) {
            $message .= ' Communiquez-lui son mot de passe provisoire.';
        }

        return back()->with('success', $message);
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        $shop = $request->user()->provider;
        abort_unless($shop->members()->whereKey($member->id)->exists(), 404);
        $role = $request->validate(['role' => ['required', Rule::in(array_keys(ShopTeam::ROLES))]])['role'];

        $shop->members()->updateExistingPivot($member->id, ['role' => $role]);
        ActivityLog::record('shop.member_role', $shop, ['employé' => $member->email, 'rôle' => ShopTeam::label($role)]);

        return back()->with('success', "{$member->name} est maintenant ".ShopTeam::label($role).'.');
    }

    public function destroy(Request $request, User $member): RedirectResponse
    {
        $shop = $request->user()->provider;
        abort_unless($shop->members()->whereKey($member->id)->exists(), 404);

        $shop->members()->detach($member->id);
        // Plus aucune boutique : l'employé est déconnecté partout.
        if (! $member->memberships()->exists() && ! $member->providers()->exists()) {
            $member->tokens()->delete();
            DB::table('sessions')->where('user_id', $member->id)->delete();
        }
        ActivityLog::record('shop.member_removed', $shop, ['employé' => $member->email]);

        return back()->with('success', "{$member->name} ne fait plus partie de l'équipe.");
    }

    private function maxMembers(Provider $shop): int
    {
        return $shop->planLimit('max_team_members') ?? max(1, (int) setting('max_team_members'));
    }
}
