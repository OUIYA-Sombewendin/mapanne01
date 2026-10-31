{{-- Fiche d'une station-service : l'enseigne, les carburants, les services sur place. --}}
<x-pro-profile :provider="$provider" :copy="$copy" :completion="$completion" title="Fiche de la station" :sections="[
    ['identite', 'home', 'La station'],
    ['carburants', 'flag', 'Carburants'],
    ['equipements', 'layers', 'Services sur place'],
    ['localisation', 'map', 'Adresse'],
    ['paiement', 'card', 'Paiement'],
    ['autres', 'document', 'Autres informations'],
    ['legal', 'shield', 'Entreprise'],
]">
    @include('pro.profile._identity', ['title' => 'La station', 'text' => 'Nom, enseigne et contacts de la station.', 'detailFields' => ['brand'], 'managerLabel' => 'Gérant de la station'])
    @include('pro.profile._details', ['id' => 'carburants', 'icon' => 'flag', 'title' => 'Carburants', 'text' => 'Les carburants vendus à la pompe.', 'fields' => ['fuel_types']])
    @include('pro.profile._details', ['id' => 'equipements', 'icon' => 'layers', 'title' => 'Services sur place', 'text' => 'Ce que les clients trouvent à la station.', 'fields' => ['open_24h', 'has_shop', 'has_car_wash', 'has_tire_air', 'has_lubricants', 'has_mechanic']])
    @include('pro.profile._services', ['id' => 'services', 'title' => 'Prestations proposées', 'text' => 'Pour apparaître quand un client cherche ces services.'])
    @include('pro.profile._location', ['title' => 'Adresse de la station'])
    @include('pro.profile._payment', ['text' => 'Moyens de paiement acceptés à la pompe et à la boutique.'])
    @include('pro.profile._custom')
    @include('pro.profile._legal', ['title' => 'Entreprise'])
</x-pro-profile>
