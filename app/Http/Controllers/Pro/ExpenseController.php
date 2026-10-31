<?php

namespace App\Http\Controllers\Pro;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Support\Listing;
use App\Support\ShopReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $provider = $request->user()->provider;
        $report = ShopReport::forPeriod($provider, (string) $request->query('period', 'month'), $request->query('from'), $request->query('to'));

        return view('pro.manage.expenses', [
            'provider' => $provider,
            'report' => $report,
            'period' => (string) $request->query('period', 'month'),
            'expenses' => Listing::sort($provider->expenses()
                ->whereBetween('spent_at', [$report->from->toDateString(), $report->to->toDateString()])
                ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
                ->when($request->query('payment'), fn ($q, $m) => $q->where('payment_method', $m))
                ->when($request->query('q'), fn ($q, $s) => $q->where('description', 'ilike', Listing::like($s))), $request, [
                    'date' => 'spent_at',
                    'category' => 'category',
                    'amount' => 'amount',
                ], 'date')
                ->paginate(40)
                ->withQueryString(),
            'byCategory' => $report->expensesByCategory(),
            'categories' => $this->categories($provider),
            'paymentMethods' => (array) setting('payment_methods'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $provider = $request->user()->provider;
        $data = $request->validate([
            'spent_at' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', 'string', 'max:60', Rule::in($this->categories($provider))],
            'amount' => ['required', 'integer', 'min:1', 'max:500000000'],
            'description' => ['nullable', 'string', 'max:200'],
            'payment_method' => ['nullable', 'string', 'max:60'],
        ]);

        $provider->expenses()->create($data);

        return back()->with('success', 'Dépense enregistrée.');
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse
    {
        abort_unless($expense->provider_id === $request->user()->provider->id, 404);
        $expense->delete();

        return back()->with('success', 'Dépense supprimée.');
    }

    /** Catégories définies par l'admin + celles déjà utilisées par le prestataire. */
    private function categories($provider): array
    {
        return collect((array) setting('expense_categories'))
            ->merge($provider->expenses()->distinct()->pluck('category'))
            ->unique()->values()->all();
    }
}
