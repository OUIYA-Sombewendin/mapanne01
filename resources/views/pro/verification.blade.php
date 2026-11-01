<x-layouts.pro title="Badges de confiance">
    <section class="admin-hero on-color relative overflow-hidden rounded-3xl px-6 py-6 text-white shadow-lg sm:px-8">
        <div class="pointer-events-none absolute -top-16 -right-10 size-48 rounded-full bg-white/5"></div>
        <h1 class="text-2xl font-extrabold sm:text-3xl">🛡️ Badges de confiance</h1>
        <p class="mt-1 max-w-2xl text-white/85">Envoyez vos justificatifs : après vérification par l'équipe {{ setting('site_name') }}, un badge apparaît sur votre fiche et dans les résultats de recherche. Les conducteurs choisissent plus volontiers un prestataire vérifié.</p>
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($kinds as [, $badge, $column])
                <span @class(['rounded-full px-3 py-1 text-sm font-semibold', 'bg-white text-emerald-800' => $provider->{$column}, 'bg-white/15 text-white/70' => ! $provider->{$column}])>{{ $badge }}</span>
            @endforeach
        </div>
    </section>

    <div class="mt-6 grid gap-5 md:grid-cols-2">
        @foreach ($kinds as $kind => [$label, $badge, $column, $help])
            @php($doc = $documents[$kind] ?? null)
            <section class="card flex flex-col p-6">
                @include('pro._section-head', ['icon' => ['identity' => 'users', 'business' => 'document', 'electric' => 'wrench'][$kind] ?? 'map', 'title' => $label, 'text' => $help])

                @if ($provider->{$column})
                    <p class="rounded-xl bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ $badge }} depuis le {{ $provider->{$column}->format('d/m/Y') }}</p>
                @elseif ($doc)
                    <div class="flex items-center justify-between gap-2 rounded-xl bg-ink-50 p-3 text-sm">
                        <span>Envoyé le {{ $doc->created_at->format('d/m/Y') }}</span>
                        <span class="chip {{ $doc->statusClass() }}">{{ $doc->statusLabel() }}</span>
                    </div>
                    @if ($doc->status === 'rejected')
                        <p class="mt-2 text-sm text-red-700">Motif : {{ $doc->rejected_reason }}</p>
                    @elseif ($doc->status === 'pending')
                        <form method="POST" action="{{ route('pro.verification.destroy', $doc) }}" class="mt-2">@csrf @method('DELETE')<button class="text-xs text-ink-500 underline">Retirer ce document</button></form>
                    @endif
                @endif

                @unless ($provider->{$column})
                    <form method="POST" action="{{ route('pro.verification.store') }}" enctype="multipart/form-data" class="mt-4 flex flex-1 flex-col gap-3" x-data="{ name: '' }">
                        @csrf
                        <input type="hidden" name="kind" value="{{ $kind }}">
                        @if ($kind !== 'workshop')
                            <input name="number" class="input" maxlength="60" placeholder="{{ $kind === 'identity' ? 'Numéro de la CNIB (facultatif)' : 'Numéro RCCM ou IFU (facultatif)' }}">
                        @endif
                        <label class="flex cursor-pointer flex-col items-center gap-1 rounded-2xl border-2 border-dashed border-ink-200 p-4 text-center text-sm transition hover:border-accent-400 hover:bg-accent-50/50">
                            <span class="text-3xl">{{ $kind === 'workshop' ? '📷' : '🪪' }}</span>
                            <span class="font-semibold text-accent-700" x-text="name || 'Choisir une photo'">Choisir une photo</span>
                            <input type="file" name="file" accept="image/jpeg,image/png,image/webp" class="sr-only" required @change="name = $event.target.files[0]?.name || ''">
                        </label>
                        @if ($errors->any() && old('kind') === $kind)
                            @foreach ($errors->all() as $message) <p class="field-error">{{ $message }}</p> @endforeach
                        @endif
                        <button class="btn btn-primary mt-auto">{{ $doc ? 'Envoyer un nouveau document' : 'Envoyer' }}</button>
                    </form>
                @endunless
            </section>
        @endforeach
    </div>
    <p class="mt-4 text-center text-sm text-ink-500">🔒 Vos documents ne sont jamais publiés : seule l'équipe de vérification les voit.</p>
</x-layouts.pro>
