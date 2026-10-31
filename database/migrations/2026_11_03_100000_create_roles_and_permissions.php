<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rôles de l'équipe d'administration. Les permissions possibles sont définies dans App\Support\Permissions.
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->jsonb('permissions')->default('[]');
            $table->boolean('is_system')->default(false); // super-administrateur : non modifiable, non supprimable
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('admin_role_id')->nullable()->after('role')->constrained('roles')->nullOnDelete();
        });

        $now = now();
        $roles = [
            ['super-admin', 'Super-administrateur', 'Accès complet, y compris les rôles, les sauvegardes et la configuration.', ['*'], true],
            ['moderateur', 'Modérateur', 'Valide les prestataires, modère les avis, les signalements et les produits.', [
                'history.view', 'providers.view', 'providers.manage', 'users.view', 'moderation.manage', 'marketplace.manage',
            ], false],
            ['commercial', 'Commercial', 'Suit les prestataires et gère les abonnements.', [
                'providers.view', 'users.view', 'subscriptions.manage', 'data.export',
            ], false],
            ['support', 'Support', 'Consulte les comptes et aide les utilisateurs.', [
                'providers.view', 'users.view', 'users.manage',
            ], false],
        ];
        foreach ($roles as [$slug, $name, $description, $permissions, $system]) {
            DB::table('roles')->insert([
                'slug' => $slug, 'name' => $name, 'description' => $description,
                'permissions' => json_encode($permissions), 'is_system' => $system,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // Les administrateurs existants gardent tous leurs droits.
        DB::table('users')->where('role', 'admin')->update([
            'admin_role_id' => DB::table('roles')->where('slug', 'super-admin')->value('id'),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('admin_role_id'));
        Schema::dropIfExists('roles');
    }
};
