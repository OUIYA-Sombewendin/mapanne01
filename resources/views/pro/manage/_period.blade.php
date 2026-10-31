{{-- Sélecteur de période partagé (dépenses, rapports). --}}
<form class="mb-4 flex flex-wrap items-end gap-2" x-data="{ period: '{{ $period }}' }">
    <div>
        <label class="text-xs text-ink-500" for="period">Période</label>
        <select id="period" name="period" x-model="period" class="input" @change="period !== 'custom' && $el.form.submit()">
            @foreach (\App\Support\ShopReport::PERIODS as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <template x-if="period === 'custom'">
        <div class="flex items-end gap-2">
            <div><label class="text-xs text-ink-500">Du</label><input type="date" name="from" value="{{ $report->from->toDateString() }}" class="input"></div>
            <div><label class="text-xs text-ink-500">Au</label><input type="date" name="to" value="{{ $report->to->toDateString() }}" class="input"></div>
            <button class="btn btn-dark">Afficher</button>
        </div>
    </template>
    <p class="pb-2.5 text-sm text-ink-500">{{ $report->from->format('d/m/Y') }} → {{ $report->to->format('d/m/Y') }}</p>
</form>
