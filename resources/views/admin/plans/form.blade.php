<x-layouts.admin :title="$plan->exists ? 'Modifier l\'offre' : 'Nouvelle offre'">
    <div class="mb-4"><x-back :href="route('admin.plans.index')" label="Offres" /></div>
    <h1 class="mb-5 text-2xl font-extrabold">{{ $plan->exists ? 'Offre « '.$plan->name.' »' : 'Nouvelle offre' }}</h1>

    <form method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" class="grid gap-5 xl:grid-cols-2">
        @csrf
        @if ($plan->exists) @method('PUT') @endif

        <section class="card space-y-3 p-5">
            <h2 class="font-bold">Présentation et prix</h2>
            <x-field name="name" label="Nom" :value="$plan->name" required placeholder="Ex. Standard" />
            <x-field name="description" label="Accroche" :value="$plan->description" placeholder="Ex. Gérer sa boutique au quotidien" />
            <div class="grid grid-cols-3 gap-3">
                <x-field name="price" type="number" label="Prix (FCFA)" :value="$plan->price ?? 0" min="0" required />
                <x-field name="period_days" type="number" label="Durée (jours)" :value="$plan->period_days" min="0" required hint="0 = sans fin" />
                <x-field name="position" type="number" label="Ordre" :value="$plan->position" min="0" />
            </div>
            @foreach ($limits as $key => $def)
                <x-field :name="'limits['.$key.']'" type="number" :label="$def['label']" :value="$plan->limits[$key] ?? 0" min="0" :hint="$def['help']" />
            @endforeach
            <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="checkbox" @checked(old('is_active', $plan->is_active))> Offre proposée aux prestataires</label>
            <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_highlighted" value="0"><input type="checkbox" name="is_highlighted" value="1" class="checkbox" @checked(old('is_highlighted', $plan->is_highlighted))> Mettre en avant (« Recommandé »)</label>
        </section>

        <section class="card space-y-5 p-5">
            <div>
                <h2 class="mb-2 font-bold">Fonctionnalités incluses</h2>
                <div class="space-y-2">
                    @foreach ($features as $key => $feature)
                        <label class="flex items-start gap-2 text-sm">
                            <input type="checkbox" name="features[]" value="{{ $key }}" class="checkbox mt-0.5" @checked(in_array($key, old('features', $plan->features ?? []), true))>
                            <span>{{ $feature['label'] }} @if ($feature['sellers_only'] ?? false) <span class="text-xs text-ink-400">(vendeurs de pièces)</span> @endif</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div>
                <h2 class="mb-1 font-bold">Réservée à certains types</h2>
                <p class="mb-2 text-xs text-ink-400">Ne rien cocher = offre proposée à tous les types.</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($types as $type)
                        <label class="chip chip-filter px-3 py-1.5 text-sm">
                            <input type="checkbox" name="provider_types[]" value="{{ $type->slug }}" class="sr-only" @checked(in_array($type->slug, old('provider_types', $plan->provider_types ?? []), true))>{{ $type->icon() }} {{ $type->label() }}
                        </label>
                    @endforeach
                </div>
            </div>
        </section>

        <div class="xl:col-span-2"><button class="btn btn-primary">Enregistrer</button></div>
    </form>
</x-layouts.admin>
