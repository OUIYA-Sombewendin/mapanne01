<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Couleur du badge de chaque rôle (liste des rôles, comptes). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('color', 20)->default('gray')->after('description');
        });

        foreach (['super-admin' => 'ink', 'moderateur' => 'amber', 'commercial' => 'sky', 'support' => 'emerald'] as $slug => $color) {
            DB::table('roles')->where('slug', $slug)->update(['color' => $color]);
        }
    }

    public function down(): void
    {
        Schema::table('roles', fn (Blueprint $table) => $table->dropColumn('color'));
    }
};
