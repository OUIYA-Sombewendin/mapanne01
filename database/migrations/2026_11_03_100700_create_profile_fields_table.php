<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Champs de fiche ajoutés par l'administration, par type de prestataire (ou pour tous),
 * sans toucher au code. Les réponses sont stockées dans providers.custom_fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_fields', function (Blueprint $table) {
            $table->id();
            $table->string('provider_type', 60)->nullable(); // slug du type, null = tous les types
            $table->string('key', 60);
            $table->string('label', 120);
            $table->string('type', 20);                      // text, textarea, number, bool, select, multi
            $table->jsonb('options')->nullable();            // choix des listes
            $table->string('help', 200)->nullable();
            $table->string('placeholder', 120)->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_public')->default(true);     // affiché sur la fiche publique
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['provider_type', 'key']);
        });

        Schema::table('providers', function (Blueprint $table) {
            $table->jsonb('custom_fields')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('providers', fn (Blueprint $table) => $table->dropColumn('custom_fields'));
        Schema::dropIfExists('profile_fields');
    }
};
