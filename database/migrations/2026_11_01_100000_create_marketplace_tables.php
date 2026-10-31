<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Marketplace de pièces détachées : les produits appartiennent à une boutique
 * (prestataire de type « vendeur de pièces » ou garage). Pas de paiement en ligne
 * pour l'instant : l'acheteur contacte le vendeur (appel / WhatsApp pré-rempli).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon', 16)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_category_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('brand', 60)->nullable();          // marque de la pièce (Bosch, Valeo…)
            $table->string('reference', 60)->nullable();      // référence constructeur
            $table->string('compatibility', 255)->nullable(); // « Toyota Corolla 2008-2014, Yaris »
            $table->jsonb('vehicle_types')->default('[]');    // voiture, moto, tricycle, camion
            $table->string('condition', 10)->default('new');  // new | used
            $table->unsignedInteger('price');                 // FCFA
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('on_order')->default(false);      // disponible sur commande si stock = 0
            $table->boolean('is_active')->default(true);      // mis en vente par le vendeur
            $table->boolean('is_hidden')->default(false);     // masqué par la modération
            $table->string('hidden_reason')->nullable();
            $table->unsignedInteger('contacts_count')->default(0);
            $table->timestamps();

            $table->index(['part_category_id', 'is_active', 'is_hidden']);
        });
        DB::statement('CREATE INDEX products_vehicle_types_gin ON products USING GIN (vehicle_types)');

        Schema::create('product_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_photos');
        Schema::dropIfExists('products');
        Schema::dropIfExists('part_categories');
    }
};
