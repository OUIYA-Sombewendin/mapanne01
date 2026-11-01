{{--
    Outils pour placer un point (à mettre dans un bloc x-data="locationPicker(...)") :
    recherche d'un lieu ou lien Google Maps / WhatsApp collé, et « Je suis sur place ».
--}}
<div class="mb-2 space-y-2" x-show="!locked">
    <div class="flex flex-wrap gap-2">
        <div class="relative min-w-0 flex-1">
            <input x-model="query" @keydown.enter.prevent="search()" class="input pr-10" autocomplete="off"
                   placeholder="Chercher un lieu ou coller un lien Google Maps" aria-label="Chercher un lieu ou coller un lien Google Maps">
            <button type="button" @click="search()" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-ink-400 hover:text-ink-700" aria-label="Chercher">
                <x-icon name="search" class="size-5" />
            </button>
        </div>
        <button type="button" @click="useMyPosition()" :disabled="loading" class="btn btn-ghost" x-text="loading ? 'Localisation…' : '📍 Je suis sur place'"></button>
    </div>
    <p x-show="searching" x-cloak class="text-xs text-ink-500">Recherche…</p>
    <ul x-show="results.length" x-cloak class="max-h-56 divide-y divide-ink-50 overflow-y-auto rounded-xl bg-white text-sm shadow-lg ring-1 ring-ink-100">
        <template x-for="r in results" :key="r.lat + ',' + r.lng">
            <li><button type="button" @click="pick(r)" class="block w-full px-3 py-2 text-left hover:bg-ink-50" x-text="r.label"></button></li>
        </template>
    </ul>
    <p class="text-xs text-ink-400">Astuce : dans Google Maps ou WhatsApp, partagez la position puis collez le lien ici. Vous pouvez aussi toucher la carte ou déplacer l'épingle.</p>
</div>
