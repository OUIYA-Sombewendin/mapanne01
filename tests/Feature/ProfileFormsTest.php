<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\ProfileField;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Un formulaire de fiche par métier, et des champs ajoutables depuis l'admin. */
class ProfileFormsTest extends TestCase
{
    use RefreshDatabase;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();
        $this->city = City::create(['name' => 'Ouagadougou', 'slug' => 'ouaga-f', 'latitude' => 12.37, 'longitude' => -1.52]);
    }

    private function payload(Provider $p, array $extra = []): array
    {
        return $extra + [
            'business_name' => $p->business_name, 'phone' => $p->phone, 'city_id' => $this->city->id,
            'latitude' => $p->latitude, 'longitude' => $p->longitude,
        ];
    }

    public function test_each_trade_has_its_own_form(): void
    {
        $expected = [
            ProviderType::MECHANIC => ['Ma fiche de mécanicien', 'Mon savoir-faire', 'Zone d\'intervention'],
            ProviderType::GARAGE => ['Fiche du garage', 'Atelier et équipements', 'Pont élévateur'],
            ProviderType::TOW_TRUCK => ['Fiche de dépannage', 'Flotte', 'Prix par km'],
            ProviderType::STATION => ['Fiche de la station', 'Carburants', 'Gasoil'],
            ProviderType::PARTS_SHOP => ['Fiche de la boutique', 'Ce que vous vendez', 'Ajouter une pièce'],
        ];
        $unexpected = [
            ProviderType::MECHANIC => ['Carburants', 'Pont élévateur', 'Ce que vous vendez'],
            ProviderType::GARAGE => ['Carburants', 'Prix par km', 'Ce que vous vendez'],
            ProviderType::TOW_TRUCK => ['Carburants', 'Pont élévateur'],
            ProviderType::STATION => ['Pont élévateur', 'Prix par km', 'Ce que vous vendez'],
            ProviderType::PARTS_SHOP => ['Carburants', 'Pont élévateur', 'Prix par km'],
        ];

        foreach ($expected as $type => $texts) {
            $provider = Provider::factory()->approved()->create(['type' => $type]);
            $response = $this->actingAs($provider->user)->get('/pro/fiche')->assertOk();
            foreach ($texts as $text) {
                $response->assertSee($text);
            }
            foreach ($unexpected[$type] as $text) {
                $response->assertDontSee($text);
            }
            // Le type n'est plus un champ du formulaire.
            $response->assertDontSee('name="type"', false);
        }
    }

    public function test_a_trade_only_saves_its_own_services(): void
    {
        $repair = ServiceCategory::create(['name' => 'Réparation', 'slug' => 'reparation']);
        $station = ServiceCategory::create(['name' => 'Station', 'slug' => 'station']);
        $brakes = Service::create(['service_category_id' => $repair->id, 'name' => 'Freinage', 'slug' => 'freinage-x', 'is_active' => true]);
        $fuel = Service::create(['service_category_id' => $station->id, 'name' => 'Super 91', 'slug' => 'super-x', 'is_active' => true]);

        $garage = Provider::factory()->approved()->create(['type' => ProviderType::GARAGE]);
        $this->actingAs($garage->user)->get('/pro/fiche')->assertSee('Freinage')->assertDontSee('Super 91');

        $this->put('/pro/fiche', $this->payload($garage, ['services' => [$brakes->id, $fuel->id]]))->assertSessionHasErrors('services.1');
        $this->put('/pro/fiche', $this->payload($garage, ['services' => [$brakes->id]]))->assertSessionHasNoErrors();
        $this->assertSame([$brakes->id], $garage->services()->pluck('services.id')->all());
    }

    public function test_tow_truck_details_are_spread_over_sections_and_all_saved(): void
    {
        $tow = Provider::factory()->approved()->create(['type' => ProviderType::TOW_TRUCK]);
        $this->actingAs($tow->user)->put('/pro/fiche', $this->payload($tow, ['details' => ['tow' => [
            'trucks_count' => '2', 'max_weight_tons' => '3.5', 'vehicle_types' => ['voiture'], 'available_24h' => '1',
            'base_price' => '15000', 'price_per_km' => '500', 'coverage_km' => '60',
        ]]]))->assertSessionHasNoErrors();

        $profile = $tow->fresh()->towTruckProfile;
        $this->assertSame(2, $profile->trucks_count);
        $this->assertSame(500, $profile->price_per_km);
        $this->assertSame(60, $profile->coverage_km);
        $this->assertTrue($profile->available_24h);
    }

    public function test_admin_adds_fields_to_a_trade_form(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/formulaires?type='.ProviderType::GARAGE)->assertOk()->assertSee("Places dans l'atelier")->assertSee('Pont élévateur');

        $this->post('/admin/formulaires', [
            'provider_type' => ProviderType::GARAGE, 'label' => 'Agrément ministère', 'type' => 'select',
            'options' => "Oui\nNon\nEn cours", 'is_required' => '1', 'is_public' => '1', 'is_active' => '1',
        ])->assertSessionHas('success');
        $this->post('/admin/formulaires', [
            'provider_type' => '', 'label' => 'Langues parlées', 'type' => 'multi', 'options' => "Français\nMooré\nDioula", 'is_public' => '1', 'is_active' => '1',
        ]);
        $field = ProfileField::where('key', 'agrement_ministere')->first();
        $this->assertSame(['Oui', 'Non', 'En cours'], $field->options);

        // Le garage voit les deux champs ; une station ne voit que le champ commun.
        $garage = Provider::factory()->approved()->create(['type' => ProviderType::GARAGE]);
        $this->actingAs($garage->user)->get('/pro/fiche')->assertSee('Agrément ministère')->assertSee('Langues parlées');
        $station = Provider::factory()->approved()->create(['type' => ProviderType::STATION]);
        $this->actingAs($station->user)->get('/pro/fiche')->assertDontSee('Agrément ministère')->assertSee('Langues parlées');

        // Champ obligatoire, valeurs contrôlées, puis affichage public.
        $this->actingAs($garage->user)->put('/pro/fiche', $this->payload($garage))->assertSessionHasErrors('custom.agrement_ministere');
        $this->put('/pro/fiche', $this->payload($garage, ['custom' => ['agrement_ministere' => 'Peut-être']]))->assertSessionHasErrors('custom.agrement_ministere');
        $this->put('/pro/fiche', $this->payload($garage, ['custom' => ['agrement_ministere' => 'Oui', 'langues_parlees' => ['Mooré', 'Dioula']]]))->assertSessionHasNoErrors();

        $this->assertSame('Oui', $garage->fresh()->custom_fields['agrement_ministere']);
        $this->get('/prestataires/'.$garage->slug)->assertSee('Agrément ministère')->assertSee('Mooré, Dioula');

        // Champ interne : visible dans l'admin, pas sur la fiche publique.
        $field->update(['is_public' => false]);
        $this->get('/prestataires/'.$garage->slug)->assertDontSee('Agrément ministère');
        $this->actingAs($admin)->get('/admin/prestataires/'.$garage->slug)->assertSee('Agrément ministère (interne)');
    }

    public function test_forms_admin_requires_permission(): void
    {
        $this->actingAs(User::factory()->admin('support')->create())->get('/admin/formulaires')->assertForbidden();
    }
}
