<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login:'.Str::lower($data['login']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'login' => 'Trop de tentatives. Réessayez dans '.RateLimiter::availableIn($key).' secondes.',
            ]);
        }

        $user = static::findByLogin($data['login']);

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['login' => 'Identifiants incorrects.']);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['login' => 'Votre compte a été suspendu. Contactez le support MaPanne.']);
        }

        RateLimiter::clear($key);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        // Dernière connexion précédente, affichée sur le tableau de bord admin.
        $request->session()->put('previous_login_at', $user->last_login_at?->toIso8601String());
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        if ($user->isAdmin()) {
            ActivityLog::record('auth.admin_login', $user, ['ip' => $request->ip()]);
        }

        return redirect()->intended($user->homeRoute());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /** Connexion par email (identifiant principal) ou, si l'admin l'autorise, par téléphone. */
    public static function findByLogin(string $login): ?User
    {
        if (str_contains($login, '@')) {
            return User::where('email', Str::lower(trim($login)))->first();
        }

        if (! feature('login_with_phone_enabled')) {
            return null;
        }

        $phone = Phone::normalize($login);

        return $phone ? User::where('phone', $phone)->first() : null;
    }
}
