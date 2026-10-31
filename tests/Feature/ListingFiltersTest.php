<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Filtres et tris communs à toutes les listes. */
class ListingFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_admin_list_accepts_each_sort_column_in_both_directions(): void
    {
        Provider::factory()->approved()->count(2)->create();
        Product::factory()->count(2)->create();
        $this->actingAs(User::factory()->admin()->create());

        $lists = [
            '/admin/prestataires?status=all' => ['name', 'type', 'city', 'status', 'rating', 'created', 'updated'],
            '/admin/utilisateurs' => ['name', 'email', 'role', 'created', 'login', 'active'],
            '/admin/pieces' => ['name', 'shop', 'price', 'stock', 'views', 'contacts', 'created'],
            '/admin/avis' => ['date', 'rating', 'provider'],
            '/admin/signalements' => ['date', 'reason'],
            '/admin/abonnements?status=all' => ['date', 'start', 'end', 'amount'],
            '/admin/historique' => ['date', 'action', 'user'],
            '/admin/verrous' => ['date', 'type'],
            '/admin/contenus' => ['title', 'slug', 'position', 'updated'],
            '/admin/sauvegardes' => ['date', 'size'],
        ];
        foreach ($lists as $url => $columns) {
            foreach ($columns as $column) {
                foreach (['asc', 'desc'] as $dir) {
                    $this->get($url.(str_contains($url, '?') ? '&' : '?')."sort=$column&dir=$dir")->assertOk();
                }
            }
            // Une colonne inconnue est ignorée (tri par défaut), sans erreur.
            $this->get($url.(str_contains($url, '?') ? '&' : '?').'sort=password&dir=asc')->assertOk();
        }
    }

    public function test_admin_provider_filters_combine(): void
    {
        $ouaga = City::create(['name' => 'Ouagadougou', 'slug' => 'ouaga-l', 'latitude' => 12.37, 'longitude' => -1.52]);
        $bobo = City::create(['name' => 'Bobo-Dioulasso', 'slug' => 'bobo-l', 'latitude' => 11.18, 'longitude' => -4.29]);
        Provider::factory()->approved()->create(['business_name' => 'Garage Nuit Ouaga', 'type' => ProviderType::GARAGE, 'city_id' => $ouaga->id, 'night_service' => true]);
        Provider::factory()->approved()->create(['business_name' => 'Garage Jour Ouaga', 'type' => ProviderType::GARAGE, 'city_id' => $ouaga->id]);
        Provider::factory()->approved()->create(['business_name' => 'Garage Nuit Bobo', 'type' => ProviderType::GARAGE, 'city_id' => $bobo->id, 'night_service' => true]);

        $this->actingAs(User::factory()->admin()->create())
            ->get("/admin/prestataires?status=all&city={$ouaga->id}&night=1")
            ->assertSee('Garage Nuit Ouaga')->assertDontSee('Garage Jour Ouaga')->assertDontSee('Garage Nuit Bobo')
            ->assertSee('Réinitialiser');

        // Tri par nom : ordre alphabétique.
        $html = $this->get('/admin/prestataires?status=all&sort=name&dir=asc')->getContent();
        $this->assertLessThan(strpos($html, 'Garage Nuit Bobo'), strpos($html, 'Garage Jour Ouaga'));
    }

    public function test_pro_lists_sort_and_filter(): void
    {
        $shop = Provider::factory()->approved()->create(['type' => ProviderType::PARTS_SHOP]);
        Product::factory()->for($shop, 'provider')->create(['name' => 'Bougie', 'price' => 1500, 'stock' => 0, 'shelf' => 'A1']);
        Product::factory()->for($shop, 'provider')->create(['name' => 'Alternateur', 'price' => 90000, 'stock' => 4, 'shelf' => 'B2']);
        $this->actingAs($shop->user);

        foreach (['/pro/catalogue' => ['created', 'name', 'price', 'stock', 'views', 'contacts'],
            '/pro/gestion/stock' => ['name', 'shelf', 'stock', 'cost', 'price', 'margin', 'value'],
            '/pro/gestion/stock/mouvements' => ['date', 'quantity', 'product'],
            '/pro/gestion/factures' => ['number', 'date', 'customer', 'total', 'balance', 'status'],
            '/pro/gestion/depenses' => ['date', 'category', 'amount']] as $url => $columns) {
            foreach ($columns as $column) {
                $this->get("$url?sort=$column&dir=asc")->assertOk();
            }
        }

        // Le nom figure aussi dans le formulaire de mouvement : on vérifie les lignes du tableau.
        $row = fn ($name) => '<span class="font-semibold">'.$name.'</span>';
        $this->get('/pro/gestion/stock?shelf=B2')->assertSee($row('Alternateur'), false)->assertDontSee($row('Bougie'), false);
        $html = $this->get('/pro/gestion/stock?sort=price&dir=asc')->getContent();
        $this->assertLessThan(strpos($html, $row('Alternateur')), strpos($html, $row('Bougie')));
        $this->get('/pro/catalogue?filter=out')->assertSee('Bougie')->assertDontSee('Alternateur');
    }
}
