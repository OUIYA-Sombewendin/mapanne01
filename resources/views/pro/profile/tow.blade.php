{{-- Fiche d'un dépanneur : la flotte, les tarifs, la zone couverte. Un client en panne veut savoir vite. --}}
<x-pro-profile :provider="$provider" :copy="$copy" :completion="$completion" title="Fiche de dépannage" :sections="[
    ['identite', 'phone', 'Service de dépannage'],
    ['flotte', 'providers', 'Flotte'],
    ['tarifs', 'card', 'Tarifs'],
    ['services', 'layers', 'Interventions'],
    ['zone', 'map', 'Base et zone couverte'],
    ['paiement', 'card', 'Paiement'],
    ['autres', 'document', 'Autres informations'],
    ['legal', 'shield', 'Statut légal'],
]">
    @include('pro.profile._identity', ['title' => 'Service de dépannage', 'text' => 'Le numéro d\'appel est l\'information la plus importante : vérifiez-le.', 'phoneLabel' => 'Numéro d\'urgence (appels)'])
    @include('pro.profile._details', ['id' => 'flotte', 'icon' => 'providers', 'title' => 'Flotte', 'text' => 'Vos dépanneuses et ce qu\'elles peuvent remorquer.', 'fields' => ['trucks_count', 'max_weight_tons', 'vehicle_types', 'available_24h']])
    @include('pro.profile._details', ['id' => 'tarifs', 'icon' => 'card', 'title' => 'Tarifs', 'text' => 'Affichés sur votre fiche : un prix clair rassure et évite les mauvaises surprises.', 'fields' => ['base_price', 'price_per_km']])
    @include('pro.profile._services', ['title' => 'Interventions', 'text' => 'Ce que vous faites sur place ou au remorquage.'])
    @include('pro.profile._location', ['id' => 'zone', 'title' => 'Base et zone couverte', 'text' => 'Votre point de départ et la distance que vous acceptez de parcourir.', 'detailFields' => ['coverage_km']])
    @include('pro.profile._payment')
    @include('pro.profile._custom')
    @include('pro.profile._legal')
</x-pro-profile>
