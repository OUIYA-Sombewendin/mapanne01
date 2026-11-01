<x-layouts.admin title="Réglages">
    <h1 class="mb-1 text-2xl font-extrabold">Réglages et options</h1>
    <p class="mb-5 text-sm text-ink-500">Tout ce qui se configure sans modifier le code. Les changements s'appliquent immédiatement.</p>

    <div class="grid gap-5 lg:grid-cols-[220px_minmax(0,1fr)]">
        <nav class="flex gap-1 overflow-x-auto lg:flex-col">
            @foreach ($groups as $key => $group)
                <a href="{{ route('admin.settings.index', $key) }}" @class([
                    'flex items-center gap-2 rounded-lg px-3 py-2 text-sm whitespace-nowrap',
                    'bg-white font-semibold shadow-sm ring-1 ring-ink-100' => $current === $key,
                    'text-ink-600 hover:bg-white' => $current !== $key,
                ])><span>{{ $group['icon'] }}</span>{{ $group['label'] }}</a>
            @endforeach
        </nav>

        <div class="space-y-5">
            <form method="POST" action="{{ route('admin.settings.update', $current) }}" enctype="multipart/form-data" class="card divide-y divide-ink-50">
                @csrf @method('PUT')
                <h2 class="p-5 text-lg font-bold">{{ $groups[$current]['icon'] }} {{ $groups[$current]['label'] }}</h2>

                @foreach ($definitions as $key => $def)
                    @php
                        $value = old($key, $values[$key] ?? $def['default']);
                        $display = match ($def['type']) {
                            'int_list' => is_array($value) ? implode(', ', $value) : $value,
                            'list' => is_array($value) ? implode("\n", $value) : $value,
                            'pairs' => is_array($value) ? collect($value)->map(fn ($v, $k) => $k.' | '.$v)->implode("\n") : $value,
                            'secret' => '',
                            default => $value,
                        };
                    @endphp
                    <div class="grid gap-2 p-5 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)] sm:items-start">
                        <div>
                            <label for="{{ $key }}" class="text-sm font-medium">{{ $def['label'] }}</label>
                            @isset($def['help']) <p class="text-xs text-ink-400">{{ $def['help'] }}</p> @endisset
                        </div>
                        <div>
                            @switch($def['type'])
                                @case('bool')
                                    <label class="inline-flex cursor-pointer items-center gap-3">
                                        <input type="hidden" name="{{ $key }}" value="0">
                                        <input id="{{ $key }}" type="checkbox" name="{{ $key }}" value="1" class="peer sr-only" @checked($value)>
                                        <span class="relative h-6 w-11 rounded-full bg-ink-200 transition peer-checked:bg-brand-500 after:absolute after:top-0.5 after:left-0.5 after:size-5 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-5"></span>
                                        <span class="text-sm text-ink-500 peer-checked:hidden">Désactivé</span>
                                        <span class="hidden text-sm font-medium text-brand-600 peer-checked:inline">Activé</span>
                                    </label>
                                    @break
                                @case('select')
                                    <select id="{{ $key }}" name="{{ $key }}" class="input">
                                        @foreach ($def['options'] as $optValue => $optLabel)
                                            <option value="{{ $optValue }}" @selected($value === $optValue)>{{ $optLabel }}</option>
                                        @endforeach
                                    </select>
                                    @break
                                @case('text')
                                @case('list')
                                @case('pairs')
                                    <textarea id="{{ $key }}" name="{{ $key }}" rows="{{ $def['type'] === 'text' ? 3 : 6 }}" class="input font-mono text-xs">{{ $display }}</textarea>
                                    @if ($def['type'] === 'list') <p class="mt-1 text-xs text-ink-400">Une valeur par ligne.</p> @endif
                                    @if ($def['type'] === 'pairs') <p class="mt-1 text-xs text-ink-400">Une ligne par élément : <code>clé | libellé</code></p> @endif
                                    @break
                                @case('color')
                                    <div x-data="{ c: '{{ $value }}' }" class="space-y-2">
                                        <div class="flex items-center gap-2">
                                            <input type="color" x-model="c" class="h-10 w-14 cursor-pointer rounded-lg border border-ink-200 bg-white p-1" aria-label="Choisir">
                                            <input id="{{ $key }}" name="{{ $key }}" x-model="c" class="input max-w-36 font-mono" pattern="#[0-9a-fA-F]{6}">
                                            <button type="button" @click="c = '{{ $def['default'] }}'" class="text-xs text-ink-500 underline">Par défaut</button>
                                        </div>
                                        <div class="flex gap-1.5">
                                            @foreach ($def['presets'] ?? [] as $preset)
                                                <button type="button" @click="c = '{{ $preset }}'" class="size-7 rounded-full ring-2 ring-white shadow" style="background: {{ $preset }}" :class="c === '{{ $preset }}' && 'outline-2 outline-offset-1 outline-ink-800'" aria-label="{{ $preset }}"></button>
                                            @endforeach
                                        </div>
                                    </div>
                                    @break
                                @case('image')
                                    <div class="flex items-center gap-3">
                                        @if (filled($value))
                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($value) }}" alt="" class="size-14 rounded-xl bg-ink-50 object-contain ring-1 ring-ink-100">
                                        @endif
                                        <div class="space-y-1">
                                            <input id="{{ $key }}" type="file" name="{{ $key }}" accept="image/png,image/jpeg,image/webp" class="text-sm">
                                            @if (filled($value))
                                                <label class="flex items-center gap-2 text-xs text-ink-500"><input type="checkbox" name="{{ $key }}_remove" value="1" class="checkbox size-4"> Supprimer l'image actuelle</label>
                                            @endif
                                        </div>
                                    </div>
                                    @break
                                @case('secret')
                                    <input id="{{ $key }}" type="password" name="{{ $key }}" class="input" autocomplete="new-password"
                                           placeholder="{{ filled($values[$key] ?? null) ? '•••••••• (laisser vide pour conserver)' : 'Non défini' }}">
                                    @break
                                @default
                                    <input id="{{ $key }}" name="{{ $key }}" value="{{ $display }}"
                                           type="{{ in_array($def['type'], ['int', 'float'], true) ? 'number' : ($def['type'] === 'email' ? 'email' : 'text') }}"
                                           @if ($def['type'] === 'float') step="any" @endif class="input">
                                    @if ($def['type'] === 'int_list') <p class="mt-1 text-xs text-ink-400">Nombres séparés par des virgules.</p> @endif
                            @endswitch
                            @error($key) <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endforeach

                <div class="flex justify-end p-5"><button class="btn btn-primary">Enregistrer</button></div>
            </form>

            @if ($current === 'appearance')
                <section class="card p-5">
                    <h2 class="mb-3 font-bold">Aperçu des couleurs</h2>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" class="btn btn-primary">Bouton principal</button>
                        <button type="button" class="btn btn-dark">Bouton sombre</button>
                        <span class="chip bg-brand-100 text-brand-800">Étiquette</span>
                        <a class="text-brand-600 underline">Lien</a>
                        <x-logo class="size-10" />
                    </div>
                    <p class="mt-2 text-xs text-ink-400">L'aperçu reflète les couleurs enregistrées. Les nuances claires et foncées sont calculées automatiquement.</p>
                </section>
            @endif

            @if ($current === 'mail')
                {{-- État réel des envois : ce qui part vraiment, et ce qui reste bloqué --}}
                <section class="card p-5">
                    <h2 class="mb-1 font-bold">État des envois</h2>
                    <p class="mb-3 text-sm text-ink-500">Tant qu'un canal est en rouge, le site n'annonce pas « envoyé » aux utilisateurs pour ce canal.</p>
                    <ul class="divide-y divide-ink-50">
                        @foreach (\App\Support\Delivery::status() as [$channel, $ok, $detail])
                            <li class="flex flex-wrap items-start gap-3 py-2.5 text-sm">
                                <span class="w-44 shrink-0 font-semibold">{{ $channel }}</span>
                                <span @class(['chip shrink-0', 'bg-emerald-100 text-emerald-800' => $ok, 'bg-red-100 text-red-800' => ! $ok])>{{ $ok ? 'En service' : 'Inactif' }}</span>
                                <span class="min-w-0 flex-1 text-ink-600">{{ $detail }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <form method="POST" action="{{ route('admin.settings.test-mail') }}" class="card flex flex-wrap items-end gap-3 p-5">
                    @csrf
                    <div class="flex-1"><x-field name="email" type="email" label="Envoyer un email de test à" :value="auth()->user()->email" required /></div>
                    <button class="btn btn-dark">Envoyer le test</button>
                </form>
            @endif

            @if ($current === 'general')
                <section class="card p-5">
                    <h2 class="mb-1 font-bold">Modules prévus</h2>
                    <p class="mb-3 text-sm text-ink-500">Ces fonctionnalités demandent un développement avant de pouvoir être activées ici.</p>
                    <ul class="grid gap-2 sm:grid-cols-2">
                        @foreach ($upcoming as [$name, $desc])
                            <li class="rounded-xl border border-dashed border-ink-200 p-3 text-sm">
                                <span class="font-semibold">{{ $name }}</span> <span class="chip bg-ink-100 text-ink-500">à venir</span>
                                <p class="text-xs text-ink-500">{{ $desc }}</p>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>
</x-layouts.admin>
