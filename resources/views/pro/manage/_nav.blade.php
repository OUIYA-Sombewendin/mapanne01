{{-- Sous-menu de la gestion. Une entrée non incluse dans l'offre affiche un cadenas et mène à la page Abonnement. --}}
@php
    $provider = auth()->user()->provider;
    $items = [['pro.manage.index', 'Vue d\'ensemble', 'pro.manage.index', null]];
    if ($provider->canSellParts()) {
        $items[] = ['pro.manage.stock', 'Stock', 'pro.manage.stock*', 'stock'];
    }
    $items[] = ['pro.manage.invoices.index', 'Ventes et factures', 'pro.manage.invoices.*', 'invoices'];
    $items[] = ['pro.manage.expenses.index', 'Dépenses', 'pro.manage.expenses.*', 'expenses'];
    $items[] = ['pro.manage.reports', 'Rapports', 'pro.manage.reports*', 'reports'];
    // Zones visibles selon le rôle dans la boutique (employés).
    $areaOf = ['pro.manage.index' => 'reports', 'pro.manage.stock' => 'stock', 'pro.manage.invoices.index' => 'invoices', 'pro.manage.expenses.index' => 'expenses', 'pro.manage.reports' => 'reports'];
    $items = array_values(array_filter($items, fn ($i) => auth()->user()->canShop($areaOf[$i[0]])));
@endphp
@include('pro._nav')
<div class="-mt-2 mb-5 flex gap-1.5 overflow-x-auto pb-1">
    @foreach ($items as [$route, $label, $pattern, $feature])
        @php($locked = $feature && ! $provider->hasPlanFeature($feature))
        <a href="{{ route($route) }}" @class([
            'chip px-3 py-1.5 text-sm',
            'bg-ink-800 text-white' => request()->routeIs($pattern),
            'bg-white text-ink-600 ring-1 ring-ink-200 hover:ring-ink-300' => ! request()->routeIs($pattern),
        ])>{{ $label }} @if ($locked) <span title="Non inclus dans votre offre">🔒</span> @endif</a>
    @endforeach
</div>
