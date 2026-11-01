{{--
    Cadre commun des fiches pro : bandeau, sommaire, formulaire, photos et barre d'enregistrement.
    Le contenu (sections) est propre à chaque métier : resources/views/pro/profile/<métier>.blade.php
--}}
@props(['provider', 'copy', 'completion', 'sections', 'title'])
@php($sections = collect($sections)->reject(fn ($s) => $s[0] === 'autres' && \App\Models\ProfileField::forProvider($provider)->isEmpty())->all())
<x-layouts.pro :title="$title">
    {{-- Bandeau --}}
    <section class="admin-hero on-color relative overflow-hidden rounded-3xl px-6 py-6 text-white shadow-lg sm:px-8">
        <div class="relative flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-4">
                <span class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white/10 text-3xl ring-1 ring-white/20">
                    @if ($provider->logoUrl()) <img src="{{ $provider->logoUrl() }}" alt="" class="size-full object-cover"> @else {{ $provider->type->icon() }} @endif
                </span>
                <div>
                    <p class="text-sm text-white/70">{{ $title }}</p>
                    <h1 class="text-2xl font-extrabold sm:text-3xl">{{ $provider->business_name }}</h1>
                    <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-white/75">
                        <span class="chip bg-white/15 text-white">{{ $provider->type->icon() }} {{ $provider->type->label() }}</span>
                        <span class="chip bg-white/15 text-white">{{ $provider->status->label() }}</span>
                    </p>
                </div>
            </div>
            <div class="w-full max-w-xs">
                <div class="flex items-baseline justify-between text-sm"><span class="text-white/75">Fiche complète à</span><strong class="text-xl">{{ $completion['percent'] }} %</strong></div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/15"><div class="h-full rounded-full bg-brand-500" style="width: {{ $completion['percent'] }}%"></div></div>
                <a href="{{ route('providers.show', $provider) }}" target="_blank" class="mt-3 inline-flex items-center gap-1 text-sm text-white/80 underline-offset-4 hover:underline"><x-icon name="external" class="size-4" /> Voir la page publique</a>
            </div>
        </div>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-[230px_minmax(0,1fr)]">
        {{-- Sommaire --}}
        <nav class="hidden lg:block">
            <div class="card sticky top-[104px] space-y-1 p-3 text-sm">
                @foreach ($sections as [$id, $icon, $label])
                    <a href="#{{ $id }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-ink-600 hover:bg-ink-50 hover:text-ink-900"><x-icon :name="$icon" class="size-4 text-ink-400" />{{ $label }}</a>
                @endforeach
                <a href="#photos" class="flex items-center gap-3 rounded-lg px-3 py-2 text-ink-600 hover:bg-ink-50 hover:text-ink-900"><x-icon name="palette" class="size-4 text-ink-400" />Photos</a>
                <div class="mt-2 border-t border-ink-100 px-3 pt-3 text-xs text-ink-400">
                    @foreach ($completion['checks'] as $label => $ok)
                        <p @class(['text-emerald-600' => $ok])>{{ $ok ? '✓' : '○' }} {{ $label }}</p>
                    @endforeach
                </div>
                <p class="border-t border-ink-100 px-3 pt-3 text-xs text-ink-400">Votre activité a changé ? Contactez le support : {{ \App\Support\Phone::format(setting('support_phone')) }}.</p>
            </div>
        </nav>

        <div class="space-y-6">
            <form id="profile-form" method="POST" action="{{ route('pro.profile.update') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf @method('PUT')
                {{ $slot }}
            </form>

            {{-- Photos (formulaire séparé : envoi indépendant) --}}
            <section id="photos" class="card scroll-mt-28 p-6">
                @include('pro._section-head', ['icon' => 'palette', 'title' => $copy['photos'], 'text' => 'Jusqu\'à '.setting('max_photos').' photos. Des photos réelles rassurent les clients.'])
                <div class="mb-4 grid grid-cols-3 gap-2 sm:grid-cols-6">
                    @foreach ($provider->photos as $photo)
                        <div class="group relative aspect-square overflow-hidden rounded-xl">
                            <img src="{{ $photo->url() }}" alt="" class="size-full object-cover">
                            <form method="POST" action="{{ route('pro.photos.destroy', $photo) }}" class="absolute top-1 right-1">
                                @csrf @method('DELETE')
                                <button class="flex size-7 items-center justify-center rounded-full bg-black/60 text-white" aria-label="Supprimer">✕</button>
                            </form>
                        </div>
                    @endforeach
                    @for ($i = $provider->photos->count(); $i < min(6, (int) setting('max_photos')); $i++)
                        <div class="flex aspect-square items-center justify-center rounded-xl border-2 border-dashed border-ink-200 text-ink-300"><x-icon name="plus" /></div>
                    @endfor
                </div>
                @if ($provider->photos->count() < setting('max_photos'))
                    <form method="POST" action="{{ route('pro.photos.store') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                        @csrf
                        <input type="file" name="photos[]" accept="image/*" multiple required class="text-sm">
                        <button class="btn btn-dark btn-sm">Ajouter les photos</button>
                    </form>
                    @error('photos') <p class="field-error">{{ $message }}</p> @enderror
                    @error('photos.*') <p class="field-error">{{ $message }}</p> @enderror
                @endif
            </section>

            {{-- Barre d'enregistrement --}}
            <div class="sticky bottom-4 z-20">
                <div class="card flex flex-wrap items-center justify-between gap-3 px-5 py-3 shadow-lg">
                    <p class="text-sm text-ink-500">{{ $provider->locksFields()
                        ? '🔒 Le nom, le téléphone, la position et le RCCM sont vérifiés : un changement devient une demande, appliquée après accord de l’équipe.'
                        : 'Un changement de nom, de téléphone ou de position d\'une fiche validée est vérifié par l\'équipe.' }}</p>
                    <button form="profile-form" class="btn btn-primary px-6 py-3">Enregistrer</button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.pro>
