{{--
    Sous un champ vérifié d'une fiche validée (dans un bloc x-data="{ edit: … }") :
    demande en cours, et bouton pour proposer un changement. Paramètre : $field.
--}}
@if ($provider->locksFields())
    @php($requests = $provider->requestedChanges())
    @if (array_key_exists($field, $requests))
        <p class="mt-1 text-xs font-medium text-amber-700">⏳ Demande en cours : « {{ $requests[$field] ?: 'vide' }} », en attente de l’équipe.</p>
    @endif
    <button type="button" x-show="!edit" @click="edit = true; $nextTick(() => $root.querySelector('input:not([type=hidden])')?.focus())"
            class="mt-1 text-xs font-semibold text-accent-700 hover:underline">🔒 Vérifié par l’équipe · demander un changement</button>
    <p x-show="edit" x-cloak class="mt-1 text-xs text-ink-500">Votre nouvelle valeur sera appliquée après vérification par l’équipe.</p>
@endif
