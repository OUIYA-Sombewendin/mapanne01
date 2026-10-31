<x-layouts.pro title="Horaires">
    <h1 class="mb-4 text-2xl font-extrabold">Horaires d'ouverture</h1>

    <form method="POST" action="{{ route('pro.schedule.update') }}" class="card max-w-2xl p-5">
        @csrf @method('PUT')
        <p class="mb-4 text-sm text-ink-500">Pour un service de nuit, l'heure de fermeture peut être après minuit (ex. 18:00 → 02:00).</p>

        <div class="divide-y divide-ink-50">
            @foreach ($days as $num => $name)
                @php
                    $s = $schedules[$num] ?? null;
                    $allday = $s && $s->opens_at === $s->closes_at;
                @endphp
                <div class="flex flex-wrap items-center gap-3 py-3"
                     x-data="{ open: {{ old("days.$num.open", $s ? 1 : 0) ? 'true' : 'false' }}, allday: {{ old("days.$num.allday", $allday) ? 'true' : 'false' }} }">
                    <label class="flex w-32 items-center gap-2 font-medium">
                        <input type="checkbox" name="days[{{ $num }}][open]" value="1" x-model="open" class="checkbox"> {{ $name }}
                    </label>
                    <template x-if="open">
                        <div class="flex flex-1 flex-wrap items-center gap-2">
                            <input type="time" name="days[{{ $num }}][opens_at]" value="{{ old("days.$num.opens_at", $s && ! $allday ? substr($s->opens_at, 0, 5) : '08:00') }}" class="input w-28" :disabled="allday">
                            <span class="text-ink-400" x-show="!allday">→</span>
                            <input type="time" name="days[{{ $num }}][closes_at]" value="{{ old("days.$num.closes_at", $s && ! $allday ? substr($s->closes_at, 0, 5) : '18:00') }}" class="input w-28" :disabled="allday">
                            <label class="chip chip-filter px-3 py-1.5"><input type="checkbox" name="days[{{ $num }}][allday]" value="1" x-model="allday" class="sr-only"> 24h/24</label>
                        </div>
                    </template>
                    <span x-show="!open" class="text-sm text-ink-400">Fermé</span>
                </div>
                @error("days.$num") <p class="field-error">{{ $message }}</p> @enderror
            @endforeach
        </div>

        <button class="btn btn-primary mt-4">Enregistrer les horaires</button>
    </form>
</x-layouts.pro>
