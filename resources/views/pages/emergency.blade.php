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

        <p class="mt-6 rounded-xl bg-ink-100 p-4 text-sm text-ink-600">
            Le bouton <strong>SOS</strong> (alerte envoyée automatiquement aux dépanneurs proches) arrive dans une prochaine version de MaPanne.
        </p>
    </div>
</x-layouts.app>
