<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Offres d'abonnement des prestataires. Les fonctionnalités possibles sont définies dans App\Support\PlanFeatures.
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('price')->default(0);           // FCFA par période
            $table->unsignedSmallInteger('period_days')->default(30);
            $table->jsonb('features')->default('[]');
            $table->jsonb('limits')->default('{}');                // ex. {"max_products": 50}
            $table->jsonb('provider_types')->nullable();           // null = tous les types
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);         // offre appliquée sans abonnement en cours
            $table->boolean('is_highlighted')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending'); // pending, active, expired, cancelled
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('amount')->default(0);
            $table->string('payment_method', 60)->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['provider_id', 'status']);
        });

        $now = now();
        $plans = [
            ['gratuit', 'Gratuit', 'Être visible et contacté par les clients.', 0, ['catalog', 'part_requests'], ['max_products' => 30], true, false],
            ['standard', 'Standard', 'Gérer sa boutique au quotidien.', 5000, ['catalog', 'catalog_import', 'part_requests', 'stock', 'invoices', 'expenses', 'statistics'], ['max_products' => 300], false, true],
            ['premium', 'Premium', 'Toute la gestion, les rapports et la mise en avant.', 15000, ['catalog', 'catalog_import', 'part_requests', 'stock', 'invoices', 'expenses', 'reports', 'statistics', 'featured'], ['max_products' => 0], false, false],
        ];
        foreach ($plans as $i => [$slug, $name, $description, $price, $features, $limits, $default, $highlighted]) {
            DB::table('plans')->insert([
                'slug' => $slug, 'name' => $name, 'description' => $description, 'price' => $price, 'period_days' => 30,
                'features' => json_encode($features), 'limits' => json_encode($limits),
                'is_active' => true, 'is_default' => $default, 'is_highlighted' => $highlighted, 'position' => $i,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
