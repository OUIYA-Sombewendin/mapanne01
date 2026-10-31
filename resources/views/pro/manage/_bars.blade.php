{{-- Histogramme léger en CSS (aucune bibliothèque à télécharger). $series : libellé => montant --}}
@php($max = max(1, max($series ?: [0])))
@if (array_sum($series) === 0)
    <p class="rounded-xl bg-ink-50 py-8 text-center text-sm text-ink-400">Aucune vente sur la période.</p>
@else
    <div class="flex h-40 items-end gap-[2px]" role="img" aria-label="Ventes par période">
        @foreach ($series as $label => $amount)
            <div class="group relative flex h-full flex-1 items-end">
                <div class="w-full rounded-t bg-brand-500/85 transition group-hover:bg-brand-600" style="height: {{ max($amount > 0 ? 3 : 0, $amount / $max * 100) }}%"></div>
                <span class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 rounded bg-ink-900 px-2 py-1 text-[11px] whitespace-nowrap text-white group-hover:block">{{ $label }} : {{ number_format($amount, 0, ',', ' ') }} F</span>
            </div>
        @endforeach
    </div>
    <div class="mt-1 flex justify-between text-[11px] text-ink-400">
        <span>{{ array_key_first($series) }}</span><span>{{ array_key_last($series) }}</span>
    </div>
@endif
