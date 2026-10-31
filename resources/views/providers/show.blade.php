<x-layouts.app :title="$provider->business_name" :description="\Illuminate\Support\Str::limit($provider->description, 150)">
    @if ($isPreview)
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <strong>Aperçu privé.</strong> Cette fiche est « {{ $provider->status->label() }} » : elle n'est pas visible par le public.
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="space-y-5">
            {{-- En-tête --}}
            <section class="card overflow-hidden">
                @if ($provider->photos->isNotEmpty())
                    <div class="flex snap-x snap-mandatory gap-1 overflow-x-auto">
                        @foreach ($provider->photos as $photo)
                            <img src="{{ $photo->url() }}" alt="Photo de {{ $provider->business_name }}" loading="lazy" class="h-48 w-[85%] shrink-0 snap-start object-cover sm:h-60 sm:w-[48%]">
                        @endforeach
                    </div>
                @endif
                <div class="p-4 sm:p-5">
                    <div class="flex items-start gap-3">
                        <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-brand-50 text-3xl">
                            @if ($provider->logoUrl())
                                <img src="{{ $provider->logoUrl() }}" alt="" class="size-full object-cover">
                            @else
                                {{ $provider->type->icon() }}
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <h1 class="text-xl font-extrabold sm:text-2xl">{{ $provider->business_name }}</h1>
                            <p class="text-sm text-ink-500">{{ $provider->type->label() }}
                                @if ($provider->neighborhood) · {{ $provider->neighborhood->name }} @endif
                                @if ($provider->city) · {{ $provider->city->name }} @endif
                            </p>
                            <x-stars :rating="$provider->rating_avg" :count="$provider->reviews_count" class="mt-1" />
                        </div>
                        @auth
                            @if (auth()->user()->isClient() && ! $isPreview)
                                <form method="POST" action="{{ route('favorites.toggle', $provider) }}">
                                    @csrf
                                    <button class="text-2xl" title="{{ $isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris' }}">{{ $isFavorite ? '❤️' : '🤍' }}</button>
                                </form>
                            @endif
                        @endauth
                    </div>

                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <span @class(['chip', 'bg-emerald-50 text-emerald-700' => $isOpen, 'bg-ink-100 text-ink-500' => ! $isOpen])>
                            <span @class(['size-1.5 rounded-full', 'bg-emerald-500' => $isOpen, 'bg-ink-400' => ! $isOpen])></span>
                            {{ $isOpen ? 'Ouvert maintenant' : 'Fermé actuellement' }}
                        </span>
                        <span class="chip bg-ink-50 text-ink-700"><span class="size-1.5 rounded-full {{ $provider->availability->dot() }}"></span>{{ $provider->availability->label() }}</span>
                        @foreach ($provider->badges() as $badge)
                            <span class="chip {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                        @endforeach
                    </div>

                    @if ($provider->description)
                        <p class="mt-4 text-sm leading-relaxed whitespace-pre-line text-ink-700">{{ $provider->description }}</p>
                    @endif
                </div>
            </section>

            {{-- Informations propres au métier : une présentation dédiée par métier (providers/highlights/<métier>) --}}
            @php($kind = $provider->profileKind())
            @if (in_array($kind, ['mechanic', 'garage', 'tow', 'station', 'shop'], true))
                @include('providers.highlights.'.$kind)
            @elseif ($provider->details()?->exists)
                <section class="card p-4 sm:p-5">
                    <h2 class="mb-3 font-bold">{{ \App\Support\ProviderProfiles::get($kind)['label'] }} : en détail</h2>
                    <x-type-details :provider="$provider" />
                </section>
            @endif

            {{-- Boutique de pièces : le catalogue passe avant tout le reste --}}
            @if ($kind === 'shop')
                @include('providers._products')
            @endif

            {{-- Champs ajoutés par l'administration (Formulaires des métiers), réponses publiques uniquement --}}
            @php($extra = \App\Models\ProfileField::forProvider($provider)->where('is_public', true)->map(fn ($f) => [$f->label, $f->display($provider->custom_fields[$f->key] ?? null)])->filter(fn ($row) => $row[1] !== null))
            @if ($extra->isNotEmpty())
                <section class="card p-4 sm:p-5">
                    <h2 class="mb-3 font-bold">Informations complémentaires</h2>
                    <dl class="grid gap-2 text-sm sm:grid-cols-2">
                        @foreach ($extra as [$label, $value])
                            <div><dt class="text-ink-500">{{ $label }}</dt><dd class="font-medium">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                </section>
            @endif

            {{-- Services --}}
            @if ($provider->services->isNotEmpty())
                <section class="card p-4 sm:p-5">
                    <h2 class="mb-3 font-bold">Services proposés</h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($provider->services as $service)
                            <span class="chip bg-ink-50 px-3 py-1.5 text-sm text-ink-700">{{ $service->category->icon }} {{ $service->name }}</span>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($kind !== 'shop')
                @include('providers._products')
            @endif

            {{-- Avis --}}
            <section class="card p-4 sm:p-5" id="avis">
                <h2 class="mb-3 font-bold">Avis clients <span class="font-normal text-ink-400">({{ $provider->reviews_count }})</span></h2>

                @if ($canReview)
                    <form method="POST" action="{{ route('reviews.store', $provider) }}" class="mb-5 rounded-xl bg-ink-50 p-4" x-data="{ rating: {{ old('rating', $myReview?->rating ?? 0) }} }">
                        @csrf
                        <p class="text-sm font-medium">{{ $myReview ? 'Modifier mon avis' : 'Donnez votre avis' }}
                            @if ($myReview?->is_pending) <span class="chip bg-amber-100 text-amber-800">en attente de validation</span> @endif
                            @if ($myReview?->is_hidden) <span class="chip bg-ink-100 text-ink-500">masqué par la modération</span> @endif
                        </p>
                        <div class="my-2 flex gap-1 text-3xl" role="radiogroup" aria-label="Note">
                            @for ($i = 1; $i <= 5; $i++)
                                <button type="button" @click="rating = {{ $i }}" :class="rating >= {{ $i }} ? 'text-amber-400' : 'text-ink-200'" aria-label="{{ $i }} étoile{{ $i > 1 ? 's' : '' }}">★</button>
                            @endfor
                            <input type="hidden" name="rating" :value="rating">
                        </div>
                        @error('rating') <p class="field-error">{{ $message }}</p> @enderror
                        <textarea name="comment" rows="2" class="input mt-1" placeholder="Racontez votre expérience (facultatif)">{{ old('comment', $myReview?->comment) }}</textarea>
                        <div class="mt-2 flex items-center justify-end gap-2">
                            <button class="btn btn-primary btn-sm" :disabled="!rating">Publier</button>
                        </div>
                    </form>
                @elseif (! auth()->check() && ! $isPreview)
                    <p class="mb-4 rounded-xl bg-ink-50 p-3 text-sm text-ink-600"><a href="{{ route('login') }}" class="font-semibold text-brand-600">Connectez-vous</a> pour laisser un avis.</p>
                @endif

                <div class="divide-y divide-ink-50">
                    @forelse ($reviews as $review)
                        <article class="py-3">
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-semibold">{{ \Illuminate\Support\Str::of($review->user->name)->before(' ') }}</span>
                                <span class="text-amber-500">{{ str_repeat('★', $review->rating) }}<span class="text-ink-200">{{ str_repeat('★', 5 - $review->rating) }}</span></span>
                            </div>
                            @if ($review->comment)
                                <p class="mt-1 text-sm text-ink-700">{{ $review->comment }}</p>
                            @endif
                            <p class="mt-1 text-xs text-ink-400">{{ $review->created_at->translatedFormat('d F Y') }}</p>
                        </article>
                    @empty
                        <p class="text-sm text-ink-400">Aucun avis pour le moment.</p>
                    @endforelse
                </div>
                {{ $reviews->fragment('avis')->links() }}
            </section>
        </div>

        {{-- Colonne contact --}}
        <aside class="space-y-5">
            <section class="card p-4 sm:p-5 lg:sticky lg:top-20">
                <div class="grid grid-cols-2 gap-2">
                    <a href="{{ $provider->callUrl() }}" class="btn btn-primary py-3">📞 Appeler</a>
                    <a href="{{ $provider->whatsappUrl() }}" target="_blank" rel="noopener" class="btn btn-whatsapp py-3">WhatsApp</a>
                </div>
                <p class="mt-2 text-center text-sm font-semibold tracking-wide">{{ \App\Support\Phone::format($provider->phone) }}</p>

                @if ($provider->latitude)
                    <div x-data="staticMap({{ $provider->latitude }}, {{ $provider->longitude }}, @js($provider->type->icon()))" class="mt-4 overflow-hidden rounded-xl">
                        <div x-ref="map" class="h-44"></div>
                    </div>
                    <a href="{{ $provider->directionsUrl() }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm mt-2 w-full">🧭 Itinéraire</a>
                @endif
                @if ($provider->address)
                    <p class="mt-2 text-sm text-ink-600">📍 {{ $provider->address }}</p>
                @endif
                @if (! empty($provider->payment_methods))
                    <p class="mt-1 text-sm text-ink-600">💳 {{ implode(' · ', $provider->payment_methods) }}</p>
                @endif
                @if ($provider->delivery_available && feature('delivery_enabled'))
                    <p class="mt-1 text-sm text-ink-600">🛵 Livraison{{ $provider->delivery_details ? ' : '.$provider->delivery_details : '' }}</p>
                @endif

                <h3 class="mt-5 mb-2 text-sm font-bold">Horaires</h3>
                @php($byDay = $provider->schedules->keyBy('day'))
                <dl class="space-y-1 text-sm">
                    @foreach ($days as $num => $name)
                        <div @class(['flex justify-between', 'font-semibold text-ink-900' => now()->dayOfWeekIso === $num, 'text-ink-600' => now()->dayOfWeekIso !== $num])>
                            <dt>{{ $name }}</dt>
                            <dd>{{ isset($byDay[$num]) ? $byDay[$num]->label() : 'Fermé' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            @auth
                @if (! $isPreview && auth()->id() !== $provider->user_id)
                    <details class="card p-4 text-sm">
                        <summary class="cursor-pointer text-ink-500">⚠️ Signaler un problème</summary>
                        <form method="POST" action="{{ route('providers.report', $provider) }}" class="mt-3 space-y-2">
                            @csrf
                            <select name="reason" class="input" required>
                                <option value="">Motif…</option>
                                @foreach ($reportReasons as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <textarea name="details" rows="2" class="input" placeholder="Détails (facultatif)"></textarea>
                            <button class="btn btn-ghost btn-sm w-full">Envoyer le signalement</button>
                        </form>
                    </details>
                @endif
            @endauth
        </aside>
    </div>
</x-layouts.app>
