<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Villes et quartiers : au Burkina beaucoup de gens se repèrent au quartier
 * plutôt qu'aux coordonnées GPS. Chaque quartier a un point central qui sert
 * de position de recherche quand l'utilisateur ne partage pas sa localisation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('neighborhoods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sector')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamps();

            $table->unique(['city_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('neighborhoods');
        Schema::dropIfExists('cities');
    }
};
