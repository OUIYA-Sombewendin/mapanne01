<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carnet d'entretien (rappels par date et historique des entretiens),
 * badge « Spécialiste électrique » et points de recharge / d'échange de batterie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('energy', 12)->default('thermique')->after('type');
        });

        Schema::create('vehicle_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('task', 30);
            $table->string('label', 80);
            $table->unsignedSmallInteger('every_months');
            $table->date('next_due_on');
            $table->date('last_done_on')->nullable();
            // Échéance pour laquelle le rappel a déjà été envoyé (un seul rappel par échéance).
            $table->date('notified_for')->nullable();
            $table->timestamps();
            $table->unique(['vehicle_id', 'task']);
            $table->index('next_due_on');
        });

        Schema::create('vehicle_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->date('done_on');
            $table->string('task', 30)->nullable();
            $table->string('label', 120);
            $table->unsignedInteger('cost')->nullable();
            $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_name', 120)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->index(['vehicle_id', 'done_on']);
        });

        Schema::table('providers', function (Blueprint $table) {
            $table->timestamp('ev_verified_at')->nullable();
        });

        Schema::create('charge_points', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('kind', 10); // recharge, swap, both
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address', 200)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('phone', 20)->nullable();
            $table->jsonb('vehicles')->nullable();
            $table->string('hours', 120)->nullable();
            $table->string('notes', 300)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_points');
        Schema::table('providers', fn (Blueprint $table) => $table->dropColumn('ev_verified_at'));
        Schema::dropIfExists('vehicle_logs');
        Schema::dropIfExists('vehicle_reminders');
        Schema::table('vehicles', fn (Blueprint $table) => $table->dropColumn('energy'));
    }
};
