<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verrous posés par l'administration sur un élément (prestataire, compte, produit, page…)
 * pour empêcher toute modification ou suppression par inadvertance depuis l'admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locks', function (Blueprint $table) {
            $table->id();
            $table->morphs('lockable');
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 200)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['lockable_type', 'lockable_id']);
        });

        // Le rôle Support peut accéder aux comptes pour aider les utilisateurs.
        $support = DB::table('roles')->where('slug', 'support')->first();
        if ($support) {
            $permissions = array_values(array_unique(array_merge(json_decode($support->permissions, true) ?: [], ['users.impersonate'])));
            DB::table('roles')->where('id', $support->id)->update(['permissions' => json_encode($permissions)]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('locks');
    }
};
