<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une table par catégorie de prestataire pour les informations propres à chaque métier.
 * La table `providers` garde le tronc commun (nom, contact, position, validation…),
 * ce qui permet une seule recherche géographique pour tous les types.
 * Chaque type (géré dans l'admin) est rattaché à une fiche métier via `profile_kind`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_types', function (Blueprint $table) {
            $table->string('profile_kind', 20)->nullable()->after('can_sell_parts');
        });
        foreach (['mecanicien' => 'mechanic', 'garage' => 'garage', 'depanneur' => 'tow', 'station' => 'station', 'commercant' => 'shop'] as $slug => $kind) {
            DB::table('provider_types')->where('slug', $slug)->update(['profile_kind' => $kind]);
        }

        Schema::create('mechanic_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_mobile')->default(false);
            $table->unsignedSmallInteger('travel_radius_km')->nullable();
            $table->unsignedSmallInteger('years_experience')->nullable();
            $table->jsonb('specialties')->nullable();
            $table->jsonb('vehicle_types')->nullable();
            $table->string('brands', 200)->nullable();
            $table->timestamps();
        });

        Schema::create('garage_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->unsignedSmallInteger('employees')->nullable();
            $table->boolean('has_diagnostic')->default(false);
            $table->boolean('has_lift')->default(false);
            $table->boolean('has_paint_shop')->default(false);
            $table->boolean('accepts_insurance')->default(false);
            $table->jsonb('specialties')->nullable();
            $table->string('brands', 200)->nullable();
            $table->timestamps();
        });

        Schema::create('tow_truck_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('trucks_count')->nullable();
            $table->decimal('max_weight_tons', 5, 1)->nullable();
            $table->jsonb('vehicle_types')->nullable();
            $table->unsignedSmallInteger('coverage_km')->nullable();
            $table->unsignedInteger('base_price')->nullable();
            $table->unsignedInteger('price_per_km')->nullable();
            $table->boolean('available_24h')->default(false);
            $table->timestamps();
        });

        Schema::create('station_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('brand', 60)->nullable();
            $table->jsonb('fuel_types')->nullable();
            $table->boolean('open_24h')->default(false);
            $table->boolean('has_shop')->default(false);
            $table->boolean('has_car_wash')->default(false);
            $table->boolean('has_tire_air')->default(false);
            $table->boolean('has_lubricants')->default(false);
            $table->boolean('has_mechanic')->default(false);
            $table->timestamps();
        });

        Schema::create('shop_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->unique()->constrained()->cascadeOnDelete();
            $table->jsonb('vehicle_types')->nullable();
            $table->string('brands', 200)->nullable();
            $table->boolean('sells_retail')->default(true);
            $table->boolean('sells_wholesale')->default(false);
            $table->boolean('sells_used')->default(false);
            $table->boolean('orders_parts')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['shop_profiles', 'station_profiles', 'tow_truck_profiles', 'garage_profiles', 'mechanic_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('provider_types', fn (Blueprint $table) => $table->dropColumn('profile_kind'));
    }
};
