<?php

use App\Support\PartCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue structuré : sous-catégories, code-barres et unité de vente, compatibilité
 * marque / modèle / années ; inventaire physique (comptage du rayon et correction du stock).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('part_categories', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('part_categories')->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('barcode', 40)->nullable()->after('reference');
            $table->string('unit', 10)->default('piece')->after('price');
            $table->index(['provider_id', 'barcode']);
        });

        Schema::create('product_fitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('make', 40);
            $table->string('model', 60)->nullable();
            $table->unsignedSmallInteger('year_from')->nullable();
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->index(['make', 'model']);
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope', 120);
            $table->string('status', 10)->default('open'); // open, applied, cancelled
            $table->unsignedInteger('adjusted_count')->default(0);
            $table->integer('gap_value')->default(0); // écart valorisé au prix d'achat (FCFA)
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->index(['provider_id', 'status']);
        });

        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('expected');
            $table->integer('counted')->nullable();
            $table->unique(['stock_count_id', 'product_id']);
        });

        PartCatalog::seedSubcategories();
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('product_fitments');
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['provider_id', 'barcode']);
            $table->dropColumn(['barcode', 'unit']);
        });
        Schema::table('part_categories', fn (Blueprint $table) => $table->dropConstrainedForeignId('parent_id'));
    }
};
