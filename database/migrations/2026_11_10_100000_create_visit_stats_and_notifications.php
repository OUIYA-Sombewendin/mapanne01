<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Statistiques de visite détaillées (itinéraires, provenance, heures), notifications dans l'espace
 * (validation de la fiche, demandes de modification) et stations sans module de ventes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_stats', function (Blueprint $table) {
            $table->unsignedInteger('directions')->default(0);   // clics « Itinéraire »
            // Provenance des vues de la fiche
            $table->unsignedInteger('src_search')->default(0);   // liste de résultats
            $table->unsignedInteger('src_map')->default(0);      // carte
            $table->unsignedInteger('src_home')->default(0);     // accueil, espace client
            $table->unsignedInteger('src_assist')->default(0);   // dépannage en direct
            $table->unsignedInteger('src_other')->default(0);    // Google, lien partagé, accès direct
        });

        // Vues et contacts par heure de la journée (heure de Ouagadougou).
        Schema::create('provider_stat_hours', function (Blueprint $table) {
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedTinyInteger('hour');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('contacts')->default(0);
            $table->primary(['provider_id', 'day', 'hour']);
        });

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        // Une station-service ne gère pas ses ventes sur MaPanne : on retire ventes, dépenses et rapports.
        foreach (DB::table('provider_types')->where('profile_kind', 'station')->get() as $type) {
            $modules = array_values(array_diff(json_decode($type->modules, true) ?: [], ['invoices', 'expenses', 'reports']));
            DB::table('provider_types')->where('id', $type->id)->update(['modules' => json_encode($modules)]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_stat_hours');
        Schema::table('provider_stats', fn (Blueprint $table) => $table->dropColumn(['directions', 'src_search', 'src_map', 'src_home', 'src_assist', 'src_other']));
    }
};
