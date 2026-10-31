<?php

namespace App\Http\Controllers\Pro;

use App\Http\Controllers\Controller;
use App\Models\InvoiceItem;
use App\Support\Csv;
use App\Support\ShopReport;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Rapports de gestion : chiffre d'affaires, marge, dépenses, bénéfice, export Excel. */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $provider = $request->user()->provider;
        $period = (string) $request->query('period', 'month');
        $report = ShopReport::forPeriod($provider, $period, $request->query('from'), $request->query('to'));

        return view('pro.manage.reports', [
            'provider' => $provider,
            'period' => $period,
            'report' => $report,
            'totals' => $report->totals(),
            'series' => $report->revenueSeries(),
            'top' => $report->topItems(),
            'byCategory' => $report->expensesByCategory(),
            'stockValue' => $provider->canSellParts() ? ShopReport::stockValue($provider) : null,
        ]);
    }

    public function export(Request $request, string $dataset): StreamedResponse
    {
        $provider = $request->user()->provider;
        $report = ShopReport::forPeriod($provider, (string) $request->query('period', 'month'), $request->query('from'), $request->query('to'));
        $range = [$report->from->toDateString(), $report->to->toDateString()];
        $suffix = $report->from->format('Ymd').'-'.$report->to->format('Ymd');

        return match ($dataset) {
            'ventes' => Csv::download("ventes-$suffix.csv",
                ['Date', 'Facture', 'Client', 'Article', 'Quantité', 'Prix unitaire', 'Total ligne', 'Coût d\'achat', 'Marge', 'Statut'],
                InvoiceItem::query()->with('invoice')
                    ->whereHas('invoice', fn ($q) => $q->where('provider_id', $provider->id)->sales()->whereBetween('issued_at', $range))
                    ->lazyById(500)
                    ->map(fn ($i) => [
                        $i->invoice->issued_at->format('d/m/Y'), $i->invoice->number, $i->invoice->customer_name, $i->description,
                        $i->quantity, $i->unit_price, $i->total, $i->quantity * $i->unit_cost, $i->total - $i->quantity * $i->unit_cost,
                        $i->invoice->statusLabel(),
                    ])),
            'depenses' => Csv::download("depenses-$suffix.csv",
                ['Date', 'Catégorie', 'Montant', 'Description', 'Paiement'],
                $provider->expenses()->whereBetween('spent_at', $range)->orderBy('spent_at')->lazyById(500)
                    ->map(fn ($e) => [$e->spent_at->format('d/m/Y'), $e->category, $e->amount, $e->description, $e->payment_method])),
            'stock' => Csv::download('stock-'.now()->format('Ymd').'.csv',
                ['Pièce', 'Référence', 'Emplacement', 'Stock', 'Prix d\'achat', 'Prix de vente', 'Valeur (achat)', 'Valeur (vente)'],
                $provider->products()->orderBy('name')->lazyById(500)
                    ->map(fn ($p) => [$p->name, $p->reference, $p->shelf, $p->stock, $p->cost_price, $p->price, $p->stock * (int) $p->cost_price, $p->stock * $p->price])),
        };
    }
}
