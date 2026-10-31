<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commerçants multi-boutiques : un compte peut posséder plusieurs fiches (boutiques),
 * chacune validée séparément, et y associer des employés (gérant, vendeur, caissier).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->index('user_id');
        });

        Schema::create('shop_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20); // manager, seller, cashier
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['provider_id', 'user_id']);
        });

        // Qui a fait la vente (propriétaire ou employé).
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('provider_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $table) => $table->dropConstrainedForeignId('user_id'));
        Schema::dropIfExists('shop_members');
        Schema::table('providers', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->unique('user_id');
        });
    }
};
