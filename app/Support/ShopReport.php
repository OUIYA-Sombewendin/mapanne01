<?php

namespace App\Support;

use App\Models\Provider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Chiffres de gestion d'un prestataire sur une période.
 *
 * - Chiffre d'affaires : total des factures (hors devis et factures annulées).
 * - Coût des pièces vendues : quantité × prix d'achat au moment de la vente.
 * - Marge brute = CA − coût des pièces vendues ; bénéfice net = marge brute − dépenses.
 * Les achats de stock ne sont pas des dépenses : ils deviennent un coût quand la pièce est vendue.
 */
class ShopReport
{
    public const PERIODS = [
        'month' => 'Ce mois-ci',
        'last_month' => 'Mois dernier',
        'quarter' => '3 derniers mois',
        'year' => 'Cette année',
        'custom' => 'Personnalisée',
    ];

    public function __construct(public Provider $provider, public Carbon $from, public Carbon $to) {}

    public static function forPeriod(Provider $provider, string $period, ?string $from = null, ?string $to = null): self
    {
        [$start, $end] = match ($period) {
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'quarter' => [now()->subMonthsNoOverflow(2)->startOfMonth(), now()->endOfDay()],
            'year' => [now()->startOfYear(), now()->endOfDay()],
            'custom' => [
                rescue(fn () => Carbon::parse($from)->startOfDay(), now()->startOfMonth(), false),
                rescue(fn () => Carbon::parse($to)->endOfDay(), now()->endOfDay(), false),
            ],
            default => [now()->startOfMonth(), now()->endOfDay()],
        };

        return new self($provider, $start, $end);
    }

    private function sales()
    {
        return $this->provider->invoices()->sales()->whereBetween('issued_at', [$this->from->toDateString(), $this->to->toDateString()]);
    }

    /** @return array<string, int> */
    public function totals(): array
    {
        $revenue = (int) $this->sales()->sum('total');
        $collected = (int) $this->sales()->sum('amount_paid');
        $discounts = (int) $this->sales()->sum('discount');
        $cogs = (int) DB::table('invoice_items')
            ->whereIn('invoice_id', $this->sales()->select('id'))
            ->sum(DB::raw('quantity * unit_cost'));
        $expenses = (int) $this->provider->expenses()->whereBetween('spent_at', [$this->from->toDateString(), $this->to->toDateString()])->sum('amount');
        $purchases = (int) $this->provider->stockMovements()->where('reason', 'purchase')
            ->whereBetween('created_at', [$this->from, $this->to])->sum(DB::raw('quantity * COALESCE(unit_cost, 0)'));

        return [
            'revenue' => $revenue,
            'collected' => $collected,
            'receivable' => $revenue - $collected,
            'discounts' => $discounts,
            'cogs' => $cogs,
            'gross' => $revenue - $cogs,
            'expenses' => $expenses,
            'net' => $revenue - $cogs - $expenses,
            'purchases' => $purchases,
            'invoices' => $this->sales()->count(),
        ];
    }

    /** CA par jour (ou par mois si la période dépasse 62 jours). @return array<string, int> */
    public function revenueSeries(): array
    {
        $monthly = $this->from->diffInDays($this->to) > 62;
        $format = $monthly ? 'YYYY-MM' : 'YYYY-MM-DD';
        $rows = $this->sales()
            ->selectRaw("to_char(issued_at, '{$format}') AS bucket, SUM(total) AS amount")
            ->groupBy('bucket')->pluck('amount', 'bucket');

        $series = [];
        $cursor = $this->from->copy();
        while ($cursor <= $this->to) {
            $key = $cursor->format($monthly ? 'Y-m' : 'Y-m-d');
            $label = $monthly ? $cursor->translatedFormat('M Y') : $cursor->format('d/m');
            $series[$label] = (int) ($rows[$key] ?? 0);
            $monthly ? $cursor->addMonthNoOverflow()->startOfMonth() : $cursor->addDay();
        }

        return $series;
    }

    /** Pièces et prestations les plus vendues. */
    public function topItems(int $limit = 8)
    {
        return DB::table('invoice_items')
            ->whereIn('invoice_id', $this->sales()->select('id'))
            ->selectRaw('description, SUM(quantity) AS qty, SUM(total) AS revenue, SUM(total - quantity * unit_cost) AS margin')
            ->groupBy('description')->orderByDesc('revenue')->limit($limit)->get();
    }

    /** @return array<string, int> */
    public function expensesByCategory(): array
    {
        return $this->provider->expenses()->whereBetween('spent_at', [$this->from->toDateString(), $this->to->toDateString()])
            ->selectRaw('category, SUM(amount) AS total')->groupBy('category')->orderByDesc('total')
            ->pluck('total', 'category')->map(fn ($v) => (int) $v)->all();
    }

    /** Valeur du stock (au prix d'achat et au prix de vente). */
    public static function stockValue(Provider $provider): array
    {
        $row = $provider->products()->selectRaw('COALESCE(SUM(stock * COALESCE(cost_price, 0)), 0) AS cost, COALESCE(SUM(stock * price), 0) AS retail, COALESCE(SUM(stock), 0) AS units')->first();

        return ['cost' => (int) $row->cost, 'retail' => (int) $row->retail, 'units' => (int) $row->units];
    }

    public static function money(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' F';
    }
}
