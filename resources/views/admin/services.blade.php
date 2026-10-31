<x-layouts.admin title="Services · Admin">
    <h1 class="mb-4 text-2xl font-extrabold">Catégories et services</h1>

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @foreach ($categories as $category)
                <section class="card p-5">
                    <h2 class="mb-3 font-bold">{{ $category->icon }} {{ $category->name }}</h2>
                    <ul class="divide-y divide-ink-50">
                        @foreach ($category->services as $service)
                            <li class="flex items-center justify-between py-2 text-sm">
                                <span @class(['text-ink-400 line-through' => ! $service->is_active])>{{ $service->name }}
                                    <span class="text-xs text-ink-400">· {{ $service->providers_count }} prestataire(s)</span></span>
                                <div class="flex items-center gap-1">
                                    <x-lock-button :model="$service" compact />
                                    <form method="POST" action="{{ route('admin.services.toggle', $service) }}">
                                        @csrf
                                        <button class="btn btn-ghost btn-sm">{{ $service->is_active ? 'Désactiver' : 'Activer' }}</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>

        <aside class="space-y-5">
            <form method="POST" action="{{ route('admin.services.store') }}" class="card space-y-3 p-5">
                @csrf
                <h2 class="font-bold">Nouveau service</h2>
                <select name="service_category_id" class="input" required>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                <x-field name="name" label="Nom du service" required />
                <button class="btn btn-primary w-full">Ajouter</button>
            </form>

            <form method="POST" action="{{ route('admin.categories.store') }}" class="card space-y-3 p-5">
                @csrf
                <h2 class="font-bold">Nouvelle catégorie</h2>
                <div class="grid grid-cols-[4rem_1fr] gap-2">
                    <input name="icon" class="input text-center" placeholder="🔧" maxlength="4">
                    <input name="name" class="input" placeholder="Nom" required>
                </div>
                <button class="btn btn-dark w-full">Ajouter</button>
            </form>
        </aside>
    </div>
</x-layouts.admin>
