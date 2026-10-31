<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gestion de boutique dans l'espace pro : inventaire, mouvements de stock, factures et devis, dépenses.
 * Les montants sont en FCFA (entiers).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('cost_price')->nullable()->after('price'); // prix d'achat, jamais affiché au public
            $table->unsignedSmallInteger('stock_alert')->nullable()->after('stock');
            $table->string('shelf', 60)->nullable()->after('stock_alert');     // emplacement en boutique
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10)->default('invoice'); // invoice, quote
            $table->string('number', 30);
            $table->string('status', 15)->default('unpaid'); // unpaid, partial, paid, cancelled (devis : draft, accepted, cancelled)
            $table->string('customer_name', 120)->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->date('issued_at');
            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('discount')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('amount_paid')->default(0);
            $table->string('payment_method', 60)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('converted_from_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamps();
            $table->unique(['provider_id', 'number']);
            $table->index(['provider_id', 'kind', 'issued_at']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 200);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('unit_cost')->default(0); // pour calculer la marge
            $table->unsignedInteger('total');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);                 // in, out, adjust
            $table->integer('quantity');                // signé : + entrée, - sortie
            $table->integer('stock_after');
            $table->unsignedInteger('unit_cost')->nullable();
            $table->string('reason', 30);               // purchase, sale, return, loss, inventory, initial, cancel
            $table->string('note', 200)->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['provider_id', 'created_at']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->date('spent_at');
            $table->string('category', 60);
            $table->unsignedInteger('amount');
            $table->string('description', 200)->nullable();
            $table->string('payment_method', 60)->nullable();
            $table->timestamps();
            $table->index(['provider_id', 'spent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['cost_price', 'stock_alert', 'shelf']));
    }
};
