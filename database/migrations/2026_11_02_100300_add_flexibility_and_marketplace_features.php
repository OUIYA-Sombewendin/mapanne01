<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('status');        // « à la une » (offre de visibilité)
            $table->boolean('needs_review')->default(false)->after('is_featured');  // changement sensible à revoir
            $table->jsonb('pending_changes')->nullable()->after('needs_review');    // ancien → nouveau, pour l'admin
            $table->boolean('delivery_available')->default(false);
            $table->string('delivery_details')->nullable();                        // zones, délais, frais
            $table->jsonb('payment_methods')->default('[]');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('compare_at_price')->nullable()->after('price'); // prix barré (promotion)
            $table->string('warranty', 60)->nullable()->after('condition');          // « 3 mois », « Aucune »
            $table->unsignedInteger('views_count')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('approved_at')->nullable();                            // null = en attente (pré-modération)
        });
        DB::table('products')->update(['approved_at' => now()]);

        Schema::table('reviews', function (Blueprint $table) {
            $table->boolean('is_pending')->default(false)->after('is_hidden');      // pré-modération des avis
        });

        // Demandes de pièces : le client décrit ce qu'il cherche, les boutiques proposent une offre.
        Schema::create('part_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 150);
            $table->text('details')->nullable();
            $table->string('vehicle', 120)->nullable();      // « Toyota Corolla 2010 »
            $table->string('vehicle_type', 20)->nullable();
            $table->string('contact_phone', 20)->nullable(); // pour être rappelé par les boutiques
            $table->string('photo_path')->nullable();
            $table->string('status', 20)->default('open')->index(); // open | closed | expired
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('part_request_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('price');
            $table->string('condition', 10)->default('new');
            $table->string('message', 500)->nullable();
            $table->timestamps();

            $table->unique(['part_request_id', 'provider_id']); // une offre par boutique (modifiable)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_request_offers');
        Schema::dropIfExists('part_requests');
        Schema::table('reviews', fn (Blueprint $t) => $t->dropColumn('is_pending'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['compare_at_price', 'warranty', 'views_count', 'is_featured', 'approved_at']));
        Schema::table('providers', fn (Blueprint $t) => $t->dropColumn(['is_featured', 'needs_review', 'pending_changes', 'delivery_available', 'delivery_details', 'payment_methods']));
    }
};
