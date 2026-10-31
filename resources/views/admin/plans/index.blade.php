<x-layouts.admin title="Offres">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold">Offres et fonctionnalités</h1>
            <p class="text-sm text-ink-500">Composez les offres : prix, durée et fonctionnalités incluses. Un changement s'applique immédiatement aux abonnés.</p>
        </div>
        <a href="{{ route('admin.plans.create') }}" class="btn btn-primary"><x-icon name="plus" /> Nouvelle offre</a>
    </div>

    @unless (feature('subscriptions_enabled'))
        <div class="mb-5 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Les abonnements sont <strong>désactivés</strong> : tous les prestataires ont accès à toutes les fonctionnalités gratuitement.
            @can('settings.manage') <a href="{{ route('admin.settings.index', 'subscriptions') }}" class="font-semibold underline">Activer les abonnements</a> @endcan
        </div>
    @endunless

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($plans as $plan)
            <article @class(['card flex flex-col p-5', 'ring-2 ring-brand-500' => $plan->is_highlighted, 'opacity-60' => ! $plan->is_active])>
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-bold">{{ $plan->name }}</h2>
                        <p class="text-sm text-ink-500">{{ $plan->description }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        @if ($plan->is_default) <span class="chip bg-ink-800 text-white on-color">par défaut</span> @endif
                        @unless ($plan->is_active) <span class="chip bg-ink-100 text-ink-500">inactive</span> @endunless
                    </div>
                </div>
                <p class="my-3 text-2xl font-extrabold">{{ $plan->priceLabel() }}</p>
                <ul class="flex-1 space-y-1 text-sm">
                    @foreach ($plan->featureLabels() as $label) <li class="flex gap-2"><span class="text-emerald-600">✓</span>{{ $label }}</li> @endforeach
                    <li class="flex gap-2"><span class="text-emerald-600">✓</span>{{ $plan->limit('max_products') ? $plan->limit('max_products').' produits max.' : 'Produits illimités' }}</li>
                </ul>
                <p class="mt-3 text-xs text-ink-400">
                    {{ $plan->provider_types ? 'Pour : '.collect($plan->provider_types)->map(fn ($s) => \App\Models\ProviderType::bySlug($s)?->label())->filter()->implode(', ') : 'Tous les types de prestataires' }}
                    · {{ $plan->active_count }} abonné(s) actif(s)
                </p>
                <div class="mt-3 flex gap-2 border-t border-ink-50 pt-3">
                    <a href="{{ route('admin.plans.edit', $plan) }}" class="btn btn-ghost btn-sm">Modifier</a>
                    <x-lock-button :model="$plan" compact />
                    @if (! $plan->is_default && $plan->is_active)
                        <form method="POST" action="{{ route('admin.plans.default', $plan) }}">@csrf<button class="btn btn-ghost btn-sm">Définir par défaut</button></form>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
    <p class="mt-4 text-xs text-ink-400">L'offre par défaut s'applique à tout prestataire sans abonnement en cours (à l'inscription ou à l'échéance).</p>
</x-layouts.admin>
