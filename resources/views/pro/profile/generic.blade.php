{{-- Fiche d'une activité sans formulaire métier dédié (type ajouté par l'admin sans fiche métier). --}}
<x-pro-profile :provider="$provider" :copy="$copy" :completion="$completion" title="Ma fiche" :sections="[
    ['identite', 'document', 'Identité'],
    ['services', 'layers', 'Services'],
    ['localisation', 'map', 'Localisation'],
    ['paiement', 'card', 'Paiement'],
    ['autres', 'document', 'Autres informations'],
    ['legal', 'shield', 'Statut légal'],
]">
    @include('pro.profile._identity', ['title' => 'Identité', 'text' => 'Ce que les clients voient en premier dans les résultats.'])
    @include('pro.profile._services', ['title' => 'Services proposés'])
    @include('pro.profile._location', ['title' => 'Localisation'])
    @include('pro.profile._payment')
    @include('pro.profile._custom')
    @include('pro.profile._legal')
</x-pro-profile>
