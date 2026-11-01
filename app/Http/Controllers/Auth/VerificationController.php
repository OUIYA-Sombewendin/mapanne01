<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Delivery;
use App\Support\Notifier;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect($request->user()->homeRoute())
            : view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect($request->user()->homeRoute())->with('success', 'Email confirmé, merci !');
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return back()->with('success', 'Votre adresse email est déjà confirmée.');
        }

        return Notifier::send($request->user(), new VerifyEmail)
            ? back()->with('success', 'Un nouveau lien de confirmation vient d\'être envoyé à '.$request->user()->email.'.')
            : back()->with('error', Delivery::mailFailedMessage('Le lien de confirmation'));
    }
}
