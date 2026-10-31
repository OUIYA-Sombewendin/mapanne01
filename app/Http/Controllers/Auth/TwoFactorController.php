<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Deuxième étape de connexion des administrateurs : code reçu par email. */
class TwoFactorController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        return view('auth.two-factor', ['email' => $user->email]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['login' => 'Session expirée : reconnectez-vous.']);
        }

        $code = $request->validate(['code' => ['required', 'digits:6']])['code'];
        if (! TwoFactor::verify($user, $code)) {
            ActivityLog::record('auth.two_factor_failed', $user, ['ip' => $request->ip()]);
            $left = TwoFactor::attemptsLeft($user);
            if ($left === 0) {
                $request->session()->forget(TwoFactor::SESSION_KEY);

                return redirect()->route('login')->withErrors(['login' => 'Trop d\'essais : reconnectez-vous pour recevoir un nouveau code.']);
            }

            return back()->withErrors(['code' => "Code incorrect ou expiré. Il vous reste {$left} essai(s)."]);
        }

        $pending = $request->session()->pull(TwoFactor::SESSION_KEY);

        return LoginController::completeLogin($request, $user, (bool) ($pending['remember'] ?? false));
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login');
        }
        TwoFactor::send($user);

        return back()->with('success', 'Un nouveau code vous a été envoyé.');
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get(TwoFactor::SESSION_KEY.'.id');
        $user = $id ? User::find($id) : null;

        return $user && $user->is_active ? $user : null;
    }
}
