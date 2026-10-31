<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Les types de prestataires deviennent gérables depuis l'admin (auparavant une énumération PHP).
 * providers.type garde le même identifiant (slug) et pointe désormais vers cette table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name');
            $table->string('short_name', 40)->nullable();
            $table->string('icon', 16)->default('🔧');
            $table->string('description')->nullable();
            $table->boolean('can_sell_parts')->default(false);   // accès au catalogue de pièces
            $table->boolean('is_active')->default(true);         // proposé à l'inscription et dans les filtres
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        $now = now();
        $rows = [
            ['slug' => 'mecanicien', 'name' => 'Mécanicien indépendant', 'short_name' => 'Mécanicien', 'icon' => '🔧', 'can_sell_parts' => false, 'position' => 1],
            ['slug' => 'garage', 'name' => 'Garage', 'short_name' => 'Garage', 'icon' => '🏠', 'can_sell_parts' => true, 'position' => 2],
            ['slug' => 'depanneur', 'name' => 'Dépanneur / remorqueur', 'short_name' => 'Dépanneur', 'icon' => '🚛', 'can_sell_parts' => false, 'position' => 3],
            ['slug' => 'station', 'name' => 'Station-service', 'short_name' => 'Station', 'icon' => '⛽', 'can_sell_parts' => false, 'position' => 4],
            ['slug' => 'commercant', 'name' => 'Vendeur de pièces', 'short_name' => 'Pièces', 'icon' => '⚙️', 'can_sell_parts' => true, 'position' => 5],
        ];
        DB::table('provider_types')->insert(
            array_map(fn ($r) => $r + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now], $rows)
        );

        Schema::table('providers', function (Blueprint $table) {
            $table->foreign('type')->references('slug')->on('provider_types')->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('providers', fn (Blueprint $table) => $table->dropForeign(['type']));
        Schema::dropIfExists('provider_types');
    }
};
