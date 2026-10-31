<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * L'email devient l'identifiant principal (vérification, mot de passe oublié, notifications) ;
 * le téléphone passe en second : facultatif pour les clients, toujours unique s'il est renseigné.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->change();
            $table->string('email')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('phone', 20)->nullable(false)->change();
        });
    }
};
