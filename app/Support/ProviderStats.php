<?php

namespace App\Support;

use App\Models\AssistRequest;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Statistiques d'une fiche : vues (avec leur provenance), appels, WhatsApp et itinéraires comptés par jour
 * (provider_stats) et par heure (provider_stat_hours), plus les dépannages et les avis.
 * Sert la page « Statistiques » de l'espace pro.
 */
class ProviderStats
{
    public const COUNTERS = ['views', 'calls', 'whatsapp', 'directions'];

    /** Provenance d'une vue => libellé. */
    public const SOURCES = [
        'search' => 'Liste de résultats',
        'map' => 'Carte',
        'home' => 'Accueil et espace client',
        'assist' => 'Dépannage en direct',
        'other' => 'Google, lien partagé ou accès direct',
    ];

    public static function hit(Provider $provider, string $counter, ?string $source = null): void
    {
        abort_unless(in_array($counter, self::COUNTERS, true), 422);

        $columns = [$counter];
        if ($counter === 'views') {
            $columns[] = 'src_'.(array_key_exists((string) $source, self::SOURCES) ? $source : 'other');
        }
        $insert = implode(', ', $columns);
        $values = implode(', ', array_fill(0, count($columns), '1'));
        $update = implode(', ', array_map(fn ($c) => "{$c} = provider_stats.{$c} + 1", $columns));

        DB::statement(
            "INSERT INTO provider_stats (provider_id, day, {$insert}) VALUES (?, ?, {$values})
             ON CONFLICT (provider_id, day) DO UPDATE SET {$update}",
            [$provider->id, now()->toDateString()],
        );

        // Heure de la journée : vues d'un côté, contacts (appel, WhatsApp, itinéraire) de l'autre.
        $hourColumn = $counter === 'views' ? 'views' : 'contacts';
        DB::statement(
            "INSERT INTO provider_stat_hours (provider_id, day, hour, {$hourColumn}) VALUES (?, ?, ?, 1)
             ON CONFLICT (provider_id, day, hour) DO UPDATE SET {$hourColumn} = provider_stat_hours.{$hourColumn} + 1",
            [$provider->id, now()->toDateString(), now()->hour],
        );
    }

    /** Provenance d'une visite, d'après le paramètre « src » ou la page précédente. */
    public static function sourceOf(Request $request): string
    {
        $src = (string) $request->query('src');
        if (array_key_exists($src, self::SOURCES)) {
            return $src;
        }

        $referer = (string) $request->headers->get('referer');
        if ($referer === '' || parse_url($referer, PHP_URL_HOST) !== $request->getHost()) {
            return 'other';
        }
        $path = '/'.ltrim((string) parse_url($referer, PHP_URL_PATH), '/');

        return match (true) {
            str_starts_with($path, '/recherche') => 'search',
            str_starts_with($path, '/depannage') => 'assist',
            $path === '/', str_starts_with($path, '/mon-espace'), str_starts_with($path, '/mes-') => 'home',
            default => 'other',
        };
    }

    /** Totaux sur une période [from, to[ */
    public static function totals(Provider $provider, Carbon $from, Carbon $to): array
    {
        $row = self::period($provider, $from, $to)
            ->selectRaw('COALESCE(SUM(views), 0) AS views, COALESCE(SUM(calls), 0) AS calls, COALESCE(SUM(whatsapp), 0) AS whatsapp, COALESCE(SUM(directions), 0) AS directions')
            ->first();

        $assist = AssistRequest::where('created_at', '>=', $from)->where('created_at', '<', $to);

        return [
            'views' => (int) $row->views,
            'calls' => (int) $row->calls,
            'whatsapp' => (int) $row->whatsapp,
            'directions' => (int) $row->directions,
            'assist_received' => (clone $assist)->whereHas('notifiedProviders', fn ($q) => $q->whereKey($provider->id))->count(),
            'assist_done' => (clone $assist)->where('provider_id', $provider->id)->where('status', 'done')->count(),
            'reviews' => $provider->publishedReviews()->where('created_at', '>=', $from)->where('created_at', '<', $to)->count(),
        ];
    }

    /** Vues par provenance sur une période : [clé => nombre], du plus fréquent au moins fréquent. */
    public static function sources(Provider $provider, Carbon $from, Carbon $to): array
    {
        $sums = implode(', ', array_map(fn ($k) => "COALESCE(SUM(src_{$k}), 0) AS {$k}", array_keys(self::SOURCES)));
        $row = (array) self::period($provider, $from, $to)->selectRaw($sums)->first();

        return collect($row)->map(fn ($v) => (int) $v)->sortDesc()->all();
    }

    /** Vues et contacts par heure (0 à 23) sur une période. */
    public static function hours(Provider $provider, Carbon $from, Carbon $to): array
    {
        $rows = DB::table('provider_stat_hours')->where('provider_id', $provider->id)
            ->where('day', '>=', $from->toDateString())->where('day', '<', $to->toDateString())
            ->groupBy('hour')->selectRaw('hour, SUM(views) AS views, SUM(contacts) AS contacts')->get()->keyBy('hour');

        return collect(range(0, 23))->map(fn ($h) => [
            'hour' => $h,
            'views' => (int) ($rows->get($h)->views ?? 0),
            'contacts' => (int) ($rows->get($h)->contacts ?? 0),
        ])->all();
    }

    /** Vues et contacts par jour de la semaine (1 = lundi … 7 = dimanche) sur une période. */
    public static function weekdays(Provider $provider, Carbon $from, Carbon $to): array
    {
        $rows = self::period($provider, $from, $to)
            ->groupByRaw('EXTRACT(ISODOW FROM day)')
            ->selectRaw('EXTRACT(ISODOW FROM day)::int AS dow, SUM(views) AS views, SUM(calls + whatsapp + directions) AS contacts')
            ->get()->keyBy('dow');

        return collect(range(1, 7))->map(fn ($d) => [
            'day' => $d,
            'views' => (int) ($rows->get($d)->views ?? 0),
            'contacts' => (int) ($rows->get($d)->contacts ?? 0),
        ])->all();
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
            $series[] = ['day' => $d->copy(), 'views' => (int) ($r->views ?? 0), 'contacts' => (int) (($r->calls ?? 0) + ($r->whatsapp ?? 0) + ($r->directions ?? 0))];
        }

        return $series;
    }

    private static function period(Provider $provider, Carbon $from, Carbon $to)
    {
        return DB::table('provider_stats')->where('provider_id', $provider->id)
            ->where('day', '>=', $from->toDateString())->where('day', '<', $to->toDateString());
    }
}
