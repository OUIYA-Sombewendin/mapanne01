{{-- Fiche d'un mécanicien indépendant : la personne, son savoir-faire, où elle intervient. --}}
<x-pro-profile :provider="$provider" :copy="$copy" :completion="$completion" title="Ma fiche de mécanicien" :sections="[
    ['identite', 'users', 'Qui je suis'],
    ['metier', 'wrench', 'Mon savoir-faire'],
    ['services', 'layers', 'Prestations'],
    ['zone', 'map', 'Zone d\'intervention'],
    ['paiement', 'card', 'Paiement'],
    ['autres', 'document', 'Autres informations'],
    ['legal', 'shield', 'Statut légal'],
]">
    @include('pro.profile._identity', ['title' => 'Qui je suis', 'text' => 'Les clients appellent une personne de confiance : présentez-vous.', 'managerLabel' => 'Votre nom et prénom'])
    @include('pro.profile._details', ['id' => 'metier', 'icon' => 'wrench', 'title' => 'Mon savoir-faire', 'text' => 'Expérience, véhicules et spécialités : ce que les clients regardent avant d\'appeler.', 'fields' => ['years_experience', 'vehicle_types', 'specialties', 'brands']])
    @include('pro.profile._services', ['title' => 'Prestations', 'text' => 'Les réparations que vous faites vous-même.'])
    @include('pro.profile._location', ['id' => 'zone', 'title' => 'Zone d\'intervention', 'text' => 'Votre atelier, ou votre point de départ si vous vous déplacez chez le client.', 'detailFields' => ['is_mobile', 'travel_radius_km']])
    @include('pro.profile._payment')
    @include('pro.profile._custom')
    @include('pro.profile._legal')
</x-pro-profile>
