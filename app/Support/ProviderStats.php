<?php

namespace App\Support;

use App\Models\AssistRequest;
use App\Models\Provider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Statistiques d'une fiche : vues, appels et WhatsApp comptés par jour (provider_stats),
 * plus les dépannages, avis et pièces. Sert la page « Statistiques » de l'espace pro.
 */
class ProviderStats
{
    public const COUNTERS = ['views', 'calls', 'whatsapp'];

    public static function hit(Provider $provider, string $counter): void
    {
        abort_unless(in_array($counter, self::COUNTERS, true), 422);

        DB::statement(
            "INSERT INTO provider_stats (provider_id, day, {$counter}) VALUES (?, ?, 1)
             ON CONFLICT (provider_id, day) DO UPDATE SET {$counter} = provider_stats.{$counter} + 1",
            [$provider->id, now()->toDateString()],
        );
    }

    /** Totaux sur une période [from, to[ */
    public static function totals(Provider $provider, Carbon $from, Carbon $to): array
    {
        $row = DB::table('provider_stats')->where('provider_id', $provider->id)
            ->where('day', '>=', $from->toDateString())->where('day', '<', $to->toDateString())
            ->selectRaw('COALESCE(SUM(views), 0) AS views, COALESCE(SUM(calls), 0) AS calls, COALESCE(SUM(whatsapp), 0) AS whatsapp')
            ->first();

        $assist = AssistRequest::where('created_at', '>=', $from)->where('created_at', '<', $to);

        return [
            'views' => (int) $row->views,
            'calls' => (int) $row->calls,
            'whatsapp' => (int) $row->whatsapp,
            'assist_received' => (clone $assist)->whereHas('notifiedProviders', fn ($q) => $q->whereKey($provider->id))->count(),
            'assist_done' => (clone $assist)->where('provider_id', $provider->id)->where('status', 'done')->count(),
            'reviews' => $provider->publishedReviews()->where('created_at', '>=', $from)->where('created_at', '<', $to)->count(),
        ];
    }

    /** Série quotidienne (vues et contacts) des $days derniers jours, jours vides inclus. */
    public static function daily(Provider $provider, int $days = 30): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $rows = DB::table('provider_stats')->where('provider_id', $provider->id)->where('day', '>=', $from->toDateString())
            ->get()->keyBy(fn ($r) => Carbon::parse($r->day)->toDateString());

        $series = [];
        for ($d = $from->copy(); $d->lte(now()); $d->addDay()) {
            $r = $rows->get($d->toDateString());
            $series[] = ['day' => $d->copy(), 'views' => (int) ($r->views ?? 0), 'contacts' => (int) (($r->calls ?? 0) + ($r->whatsapp ?? 0))];
        }

        return $series;
    }
}
