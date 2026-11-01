p='app/Support/ProviderProfiles.php'
s=open(p,encoding='utf-8').read()
add={
"'services' => ['reparation', 'pneumatique', 'assistance'], 'details_intro' => 'Ce que les clients regardent avant d\'appeler un mécanicien.',":
 r"""
                'pitch' => 'Recevez les appels des conducteurs en panne autour de vous.', 'phone' => 'Numéro où les clients vous joignent',
                'signup' => ['vehicle_types', 'specialties', 'is_mobile'],""",
"'services' => ['reparation', 'carrosserie', 'pneumatique', 'assistance'], 'details_intro' => 'Capacité et équipements : ce qui distingue votre garage.',":
 r"""
                'pitch' => 'Faites connaître votre garage, vos équipements et vos spécialités.', 'phone' => 'Téléphone du garage',
                'signup' => ['employees', 'has_diagnostic', 'has_paint_shop', 'accepts_insurance'],""",
"'services' => ['assistance', 'pneumatique'], 'details_intro' => 'Flotte, zone et tarifs : les clients en panne veulent savoir vite.',":
 r"""
                'pitch' => 'Soyez appelé dès qu\'un véhicule doit être remorqué près de vous.', 'phone' => 'Numéro d\'urgence',
                'signup' => ['vehicle_types', 'trucks_count', 'available_24h'],""",
"'services' => ['station', 'pneumatique', 'carrosserie'], 'details_intro' => 'Carburants et services disponibles sur place.',":
 r"""
                'pitch' => 'Signalez votre station, vos carburants et vos services aux conducteurs.', 'phone' => 'Téléphone de la station',
                'signup' => ['brand', 'fuel_types', 'open_24h'],""",
"'services' => ['pieces-detachees'], 'details_intro' => 'Ce que vous vendez et comment : détail, gros, occasion, commande.',":
 r"""
                'pitch' => 'Vendez vos pièces en ligne et gérez votre boutique : stock, factures, bénéfices.', 'phone' => 'Téléphone de la boutique',
                'signup' => ['vehicle_types', 'sells_wholesale', 'sells_used'],""",
"'services' => [], 'details_intro' => '',":
 r"""
            'pitch' => 'Faites connaître votre activité aux conducteurs en panne.', 'phone' => 'Téléphone de l\'activité',
            'signup' => [],""",
}
for k,v in add.items():
    assert s.count(k)==1,k
    s=s.replace(k,k+v)
open(p,'w',encoding='utf-8').write(s)
