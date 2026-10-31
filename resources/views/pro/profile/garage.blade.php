{{-- Fiche d'un garage : un établissement, son atelier, ses équipements, son statut. --}}
<x-pro-profile :provider="$provider" :copy="$copy" :completion="$completion" title="Fiche du garage" :sections="[
    ['identite', 'home', 'Le garage'],
    ['atelier', 'wrench', 'Atelier et équipements'],
    ['services', 'layers', 'Prestations de l\'atelier'],
    ['localisation', 'map', 'Adresse'],
    ['legal', 'shield', 'Entreprise'],
    ['paiement', 'card', 'Paiement'],
    ['autres', 'document', 'Autres informations'],
]">
    @include('pro.profile._identity', ['title' => 'Le garage', 'text' => 'Nom, enseigne et contacts de l\'établissement.', 'managerLabel' => 'Chef d\'atelier ou gérant'])
    @include('pro.profile._details', ['id' => 'atelier', 'icon' => 'wrench', 'title' => 'Atelier et équipements', 'text' => 'Capacité, équipements et spécialités : ce qui distingue votre garage.', 'fields' => ['capacity', 'employees', 'specialties', 'brands', 'has_diagnostic', 'has_lift', 'has_paint_shop', 'accepts_insurance']])
    @include('pro.profile._services', ['title' => 'Prestations de l\'atelier', 'text' => 'Réparation, carrosserie, pneus, assistance : cochez ce que l\'atelier réalise.'])
    @include('pro.profile._location', ['title' => 'Adresse du garage', 'text' => 'Placez l\'entrée du garage : les clients viennent avec leur véhicule.'])
    @include('pro.profile._legal', ['title' => 'Entreprise', 'text' => 'RCCM et IFU : la plupart des clients et des assurances les demandent à un garage. Ils apparaissent sur vos factures.'])
    @include('pro.profile._payment', ['text' => 'Moyens de paiement acceptés à la caisse du garage.'])
    @include('pro.profile._custom')
</x-pro-profile>
