<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Réglages modifiables depuis l'admin. Les valeurs absentes prennent le défaut défini dans SettingsRegistry.
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->jsonb('value')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        // Pages de contenu éditables (CGU, confidentialité, à propos, FAQ…).
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('body')->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('in_footer')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('pages')->insert([
            ['slug' => 'conditions-utilisation', 'title' => 'Conditions d\'utilisation', 'position' => 1, 'body' => "## À compléter\n\nRédigez ici les conditions d'utilisation de MaPanne depuis l'administration (Contenus).", 'is_published' => true, 'in_footer' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'confidentialite', 'title' => 'Politique de confidentialité', 'position' => 2, 'body' => "## À compléter\n\nDécrivez les données collectées, leur usage, leur durée de conservation et les droits des utilisateurs (loi burkinabè sur la protection des données personnelles, CIL).", 'is_published' => true, 'in_footer' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'a-propos', 'title' => 'À propos', 'position' => 3, 'body' => 'MaPanne aide les automobilistes et motocyclistes du Burkina Faso à trouver rapidement un professionnel vérifié près de chez eux.', 'is_published' => true, 'in_footer' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'faq', 'title' => 'Questions fréquentes', 'position' => 4, 'body' => "### Comment un prestataire est-il vérifié ?\n\nL'équipe MaPanne contrôle chaque inscription avant publication.\n\n### Le paiement se fait-il sur MaPanne ?\n\nNon, vous réglez directement le professionnel.", 'is_published' => true, 'in_footer' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
        Schema::dropIfExists('settings');
    }
};
