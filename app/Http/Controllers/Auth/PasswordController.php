<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Delivery;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Throwable;

/** Mot de passe oublié : lien de réinitialisation envoyé par email. */
class PasswordController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        if (! Delivery::mailReady()) {
            return back()->withInput()->withErrors(['email' => Delivery::mailFailedMessage('Le lien de réinitialisation')]);
        }

        try {
            Password::sendResetLink(['email' => Str::lower(trim($request->input('email')))]);
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->withErrors(['email' => 'L\'email n\'a pas pu être envoyé. Réessayez plus tard ou contactez le support.']);
        }

        // Même réponse que le compte existe ou non : on ne révèle pas quels emails sont inscrits.
        return back()->with('success', 'Si un compte existe pour cet email, un lien de réinitialisation vient d\'y être envoyé.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                $user->tokens()->delete(); // déconnecte les applications mobiles
                DB::table('sessions')->where('user_id', $user->id)->delete();
                // Le lien reçu par email prouve que l'adresse appartient bien à la personne.
                if (! $user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                }
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Mot de passe modifié. Vous pouvez vous connecter.')
            : back()->withInput($request->only('email'))->withErrors(['email' => 'Ce lien n\'est plus valide. Demandez-en un nouveau.']);
    }
}
