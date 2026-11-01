<?php

namespace App\Support;

use App\Models\AssistRequest;
use App\Models\PartRequest;
use App\Models\User;
use App\Models\VehicleReminder;

/**
 * Espace client : points d'attention (cloche, tableau de bord) et compteurs du menu.
 */
class ClientSpace
{
    /** Dépannage encore en cours pour ce client, s'il y en a un. */
    public static function activeAssist(User $user): ?AssistRequest
    {
        if (! feature('assist_enabled')) {
            return null;
        }

        return AssistRequest::where('user_id', $user->id)->whereIn('status', AssistRequest::ACTIVE)->latest()->first();
    }

    /** Rappels d'entretien échus ou proches (dans le délai de préavis). */
    public static function dueReminders(User $user)
    {
        if (! feature('logbook_enabled')) {
            return collect();
        }

        return VehicleReminder::with('vehicle')
            ->whereHas('vehicle', fn ($q) => $q->where('user_id', $user->id))
            ->whereNotNull('next_due_on')
            ->whereDate('next_due_on', '<=', now()->addDays(VehicleReminder::NOTICE_DAYS))
            ->orderBy('next_due_on')
            ->get();
    }

    /** Demandes de pièces ouvertes qui ont reçu au moins une offre. */
    public static function answeredRequests(User $user)
    {
        if (! feature('marketplace_enabled') || ! feature('part_requests_enabled')) {
            return collect();
        }

        return PartRequest::open()->where('user_id', $user->id)->whereHas('offers')->withCount('offers')->latest()->get();
    }

    /**
     * Points d'attention : [libellé, détail, lien, style] (mis en cache 60 s).
     *
     * @return list<array{0: string, 1: ?string, 2: string, 3: string}>
     */
    public static function alerts(User $user): array
    {
        return cache()->remember("client.alerts.{$user->id}", 60, function () use ($user) {
            $items = [];
            if ($assist = self::activeAssist($user)) {
                $items[] = ['🚨 Dépannage en cours', $assist->statusLabel(), route('assist.show', $assist), 'red'];
            }
            foreach (self::answeredRequests($user) as $request) {
                $items[] = ['📣 '.$request->offers_count.' offre(s) reçue(s)', $request->title, route('part-requests.show', $request), 'brand'];
            }
            foreach (self::dueReminders($user) as $reminder) {
                $items[] = [$reminder->icon().' '.$reminder->label, $reminder->vehicle->title().' · '.$reminder->dueLabel(), route('logbook.show', $reminder->vehicle), $reminder->daysLeft() < 0 ? 'red' : 'amber'];
            }
            if (! $user->hasVerifiedEmail()) {
                $items[] = ['✉️ Confirmez votre email', $user->email, route('verification.notice'), 'amber'];
            }

            return $items;
        });
    }

    public static function forget(User $user): void
    {
        cache()->forget("client.alerts.{$user->id}");
    }
}
