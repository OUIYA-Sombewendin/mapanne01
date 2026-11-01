<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Badges de confiance (documents vérifiés par l'admin) et statistiques quotidiennes des fiches.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pièces justificatives envoyées par le prestataire (fichiers sur le disque privé).
        Schema::create('provider_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);                          // identity, business, workshop
            $table->string('path');
            $table->string('number', 60)->nullable();            // n° CNIB, RCCM…
            $table->string('status', 20)->default('pending');    // pending, approved, rejected
            $table->string('rejected_reason', 255)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['provider_id', 'kind']);
        });

        Schema::table('providers', function (Blueprint $table) {
            $table->timestamp('identity_verified_at')->nullable();
            $table->timestamp('business_verified_at')->nullable();
            $table->timestamp('workshop_verified_at')->nullable();
        });

        // Une ligne par fiche et par jour : vues, appels, WhatsApp (pour les statistiques du pro).
        Schema::create('provider_stats', function (Blueprint $table) {
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('calls')->default(0);
            $table->unsignedInteger('whatsapp')->default(0);
            $table->primary(['provider_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_stats');
        Schema::table('providers', fn (Blueprint $table) => $table->dropColumn(['identity_verified_at', 'business_verified_at', 'workshop_verified_at']));
        Schema::dropIfExists('provider_documents');
    }
};
