<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dépannage en direct : un conducteur en panne envoie sa position et son problème,
 * les prestataires proches du bon métier sont prévenus, le premier qui accepte intervient.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assist_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();                          // suivi par le conducteur, même sans compte
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contact_name', 100);
            $table->string('contact_phone', 20);
            $table->string('vehicle_type', 20);                     // voiture, moto, tricycle, camion
            $table->string('energy', 20)->default('thermique');     // thermique, electrique
            $table->string('problem', 30);
            $table->string('description', 500)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('landmark', 200)->nullable();            // repère : « devant la pharmacie… »
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('open')->index(); // open, accepted, on_way, done, cancelled, expired
            $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('on_way_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('close_reason', 200)->nullable();
            $table->unsignedSmallInteger('notified_count')->default(0);
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        // Prestataires prévenus pour chaque demande (seuls eux peuvent l'accepter).
        Schema::create('assist_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assist_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('distance_m')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamps();
            $table->unique(['assist_request_id', 'provider_id']);
        });

        // Nouveau module : mécaniciens, garages et dépanneurs reçoivent les demandes.
        foreach (DB::table('provider_types')->whereIn('profile_kind', ['mechanic', 'garage', 'tow'])->get() as $type) {
            $modules = json_decode($type->modules, true) ?: [];
            if (! in_array('assist', $modules, true)) {
                $modules[] = 'assist';
                DB::table('provider_types')->where('id', $type->id)->update(['modules' => json_encode($modules)]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assist_dispatches');
        Schema::dropIfExists('assist_requests');
    }
};
