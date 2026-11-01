<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\SimpleMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Double vérification des administrateurs : après le mot de passe, un code à 6 chiffres
 * est envoyé par email (valable 10 minutes, 5 essais). Activable dans Réglages › Sécurité.
 */
class TwoFactor
{
    public const SESSION_KEY = 'two_factor_user';

    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public static function required(User $user): bool
    {
        return $user->isAdmin() && feature('admin_two_factor_enabled');
    }

    /** Met la connexion en attente et envoie un code. */
    public static function challenge(Request $request, User $user, bool $remember): void
    {
        $request->session()->put(self::SESSION_KEY, ['id' => $user->id, 'remember' => $remember]);
        self::send($user);
    }

    public static function send(User $user): bool
    {
        $code = (string) random_int(100000, 999999);
        Cache::put(self::key($user), ['hash' => Hash::make($code), 'attempts' => 0], now()->addMinutes(self::TTL_MINUTES));

        return Notifier::send($user, new SimpleMessage(
            'Votre code de connexion '.setting('site_name').' : '.$code,
            [
                'Code de vérification : '.$code,
                'Il est valable '.self::TTL_MINUTES.' minutes. Si vous n\'êtes pas à l\'origine de cette connexion, changez votre mot de passe.',
            ],
        ));
    }

    /** true si le code est bon ; le code est alors détruit (usage unique). */
    public static function verify(User $user, string $code): bool
    {
        $entry = Cache::get(self::key($user));
        if (! $entry || $entry['attempts'] >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (Hash::check(trim($code), $entry['hash'])) {
            Cache::forget(self::key($user));

            return true;
        }

        $entry['attempts']++;
        Cache::put(self::key($user), $entry, now()->addMinutes(self::TTL_MINUTES));

        return false;
    }

    public static function attemptsLeft(User $user): int
    {
        return max(0, self::MAX_ATTEMPTS - (int) (Cache::get(self::key($user))['attempts'] ?? 0));
    }

    private static function key(User $user): string
    {
        return 'two-factor:'.$user->id;
    }
}
