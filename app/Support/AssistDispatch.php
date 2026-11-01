<?php

namespace App\Support;

use App\Enums\Availability;
use App\Models\AssistRequest;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Notifications\AssistRequested;
use App\Notifications\AssistUpdate;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

/**
 * Dépannage en direct : choix des prestataires prévenus, acceptation (le premier gagne),
 * avancement et fermeture des demandes.
 */
class AssistDispatch
{
    /** Types dont le métier traite ce problème et qui ont le module « Dépannage en direct ». */
    public static function typesFor(string $problem): array
    {
        return ProviderType::active()
            ->filter(fn (ProviderType $t) => $t->hasModule('assist') && in_array($t->profileKind(), AssistProblems::kinds($problem), true))
            ->pluck('slug')->all();
    }

    /** Prestataires disponibles les plus proches ; le rayon est doublé une fois si personne n'est trouvé. */
    public static function candidates(AssistRequest $request): Collection
    {
        $types = self::typesFor($request->problem);
        if (! $types) {
            return collect();
        }

        $radius = max(1, (int) setting('assist_radius_km', 10));
        foreach ([$radius, $radius * 2] as $km) {
            $found = Provider::visible()
                ->whereIn('type', $types)
                ->where('availability', '!=', Availability::Offline)
                ->near($request->latitude, $request->longitude, $km)
                ->with('user')
                // Véhicule électrique : les spécialistes vérifiés sont prévenus en priorité.
                ->when($request->energy === 'electrique', fn ($q) => $q->orderByRaw('ev_verified_at IS NULL'))
                ->orderBy('distance_m')
                ->limit(max(1, (int) setting('assist_max_providers', 15)))
                ->get();
            if ($found->isNotEmpty()) {
                return $found;
            }
        }

        return collect();
    }

    /** Prévient les prestataires proches ; renvoie leur nombre. */
    public static function dispatch(AssistRequest $request): int
    {
        $providers = self::candidates($request);

        foreach ($providers as $provider) {
            $distance = (int) round((float) $provider->distance_m);
            $request->notifiedProviders()->syncWithoutDetaching([$provider->id => ['distance_m' => $distance]]);
            self::notify($provider->user, new AssistRequested($request, $distance));
        }
        $request->forceFill(['notified_count' => $providers->count()])->save();

        return $providers->count();
    }

    public static function canSee(AssistRequest $request, Provider $provider): bool
    {
        return $request->provider_id === $provider->id
            || $request->notifiedProviders()->whereKey($provider->id)->exists();
    }

    /** Le premier prestataire qui accepte obtient la demande. */
    public static function accept(AssistRequest $request, Provider $provider): bool
    {
        $won = DB::table('assist_requests')->where('id', $request->id)->where('status', 'open')->update([
            'status' => 'accepted', 'provider_id' => $provider->id, 'accepted_at' => now(), 'updated_at' => now(),
        ]) === 1;

        if ($won) {
            self::notifyDriver($request->refresh());
        }

        return $won;
    }

    /** Le prestataire avance l'intervention (en route, terminée). */
    public static function advance(AssistRequest $request, string $status): void
    {
        $request->forceFill([
            'status' => $status,
            $status === 'on_way' ? 'on_way_at' : 'done_at' => now(),
            'closed_at' => $status === 'done' ? now() : null,
        ])->save();

        self::notifyDriver($request);
    }

    /** Le prestataire ne peut finalement pas venir : la demande redevient ouverte pour les autres. */
    public static function release(AssistRequest $request, Provider $provider): void
    {
        $request->notifiedProviders()->updateExistingPivot($provider->id, ['declined_at' => now()]);
        $request->forceFill(['status' => 'open', 'provider_id' => null, 'accepted_at' => null, 'on_way_at' => null])->save();
    }

    public static function close(AssistRequest $request, string $status, ?string $reason = null): void
    {
        $request->forceFill(['status' => $status, 'closed_at' => now(), 'close_reason' => $reason])->save();
        if ($status === 'expired') {
            self::notifyDriver($request);
        }
    }

    /** Ferme les demandes restées sans réponse ; renvoie leur nombre. */
    public static function expireStale(): int
    {
        $stale = AssistRequest::where('status', 'open')
            ->where('created_at', '<', now()->subMinutes(max(5, (int) setting('assist_expire_minutes', 45))))
            ->get();
        $stale->each(fn (AssistRequest $r) => self::close($r, 'expired', 'Aucune réponse dans le délai.'));

        return $stale->count();
    }

    /** Conducteur avec compte : ses canaux (push…) ; sans compte : SMS ou WhatsApp s'ils sont activés. */
    public static function notifyDriver(AssistRequest $request): void
    {
        $notification = new AssistUpdate($request);

        if ($request->user) {
            self::notify($request->user, $notification);

            return;
        }

        $routes = array_filter([
            'sms' => Channels::smsReady() ? $request->contact_phone : null,
            'whatsapp' => Channels::whatsappReady() ? $request->contact_phone : null,
        ]);
        if ($routes) {
            self::notify(NotificationFacade::routes($routes), $notification);
        }
    }

    /** L'envoi ne bloque jamais la demande (erreurs journalisées). */
    private static function notify(?object $notifiable, Notification $notification): void
    {
        try {
            $notifiable?->notify($notification);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
