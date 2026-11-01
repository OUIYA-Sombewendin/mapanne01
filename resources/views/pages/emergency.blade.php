<x-layouts.app title="Urgence">
    <div class="mx-auto max-w-lg">
        <h1 class="text-2xl font-extrabold">🚨 Urgence</h1>
        <p class="mt-1 text-sm text-ink-500">En cas d'accident, de blessé ou de danger, appelez d'abord les secours.</p>

        <div class="mt-5 grid gap-3">
            @foreach ((array) setting('emergency_numbers') as $number => $label)
                <a href="tel:{{ $number }}" class="card flex items-center justify-between p-4 hover:ring-red-200">
                    <span class="font-semibold">{{ $label }}</span>
                    <span class="rounded-xl bg-red-600 px-4 py-2 text-xl font-extrabold text-white">{{ $number }}</span>
                </a>
            @endforeach
        </div>

        @if (feature('assist_enabled'))
            <a href="{{ route('assist.create') }}" class="mt-6 flex items-center gap-4 rounded-2xl bg-red-600 px-5 py-4 text-white shadow-lg hover:bg-red-700">
                <span class="text-3xl">🆘</span>
                <span class="flex-1"><span class="block text-lg font-extrabold">Demander un dépannage</span><span class="block text-sm text-white/85">Votre position est envoyée aux prestataires les plus proches.</span></span>
                <span class="text-2xl">→</span>
            </a>
        @endif

        <h2 class="mt-8 mb-3 text-lg font-bold">Panne sur la route ?</h2>
        <div class="grid grid-cols-2 gap-3">
            <a href="{{ route('search', ['type' => 'depanneur', 'open_now' => 1]) }}" class="card p-4 text-center">
                <span class="text-3xl">🚛</span><span class="mt-1 block text-sm font-semibold">Dépanneur ouvert</span>
            </a>
            <a href="{{ route('search', ['service' => 'crevaison-vulcanisation', 'open_now' => 1]) }}" class="card p-4 text-center">
                <span class="text-3xl">🛞</span><span class="mt-1 block text-sm font-semibold">Vulcanisateur ouvert</span>
            </a>
            <a href="{{ route('search', ['service' => 'batterie-recharge-remplacement', 'open_now' => 1]) }}" class="card p-4 text-center">
                <span class="text-3xl">🔋</span><span class="mt-1 block text-sm font-semibold">Batterie</span>
            </a>
            <a href="{{ route('search', ['night' => 1]) }}" class="card p-4 text-center">
                <span class="text-3xl">🌙</span><span class="mt-1 block text-sm font-semibold">Service de nuit</span>
            </a>
        </div>

    </div>
</x-layouts.app>
