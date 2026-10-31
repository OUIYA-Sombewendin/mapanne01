<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('type', 20)->index();
            $table->string('business_name');
            $table->string('slug')->unique();
            $table->string('manager_name')->nullable();
            $table->text('description')->nullable();
            $table->string('phone', 20);
            $table->string('whatsapp', 20)->nullable();
            $table->string('address')->nullable();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('neighborhood_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('logo_path')->nullable();

            // Formel = entreprise enregistrée (RCCM / IFU). Sert au badge, pas à la visibilité.
            $table->boolean('is_formal')->default(false);
            $table->string('rccm', 40)->nullable();
            $table->string('ifu', 40)->nullable();

            $table->boolean('night_service')->default(false);
            $table->string('availability', 20)->default('available');

            // Règle fondamentale : rien n'est public tant que status ≠ approved.
            $table->string('status', 20)->default('pending')->index();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->timestamps();
        });

        // Colonne géographique PostGIS calculée à partir de latitude/longitude :
        // Eloquent n'écrit que lat/lng, PostgreSQL maintient le point et son index.
        DB::statement(<<<'SQL'
            ALTER TABLE providers ADD COLUMN location geography(Point, 4326)
            GENERATED ALWAYS AS (
                CASE WHEN latitude IS NULL OR longitude IS NULL THEN NULL
                ELSE ST_SetSRID(ST_MakePoint(longitude::float8, latitude::float8), 4326)::geography END
            ) STORED
        SQL);
        DB::statement('CREATE INDEX providers_location_gist ON providers USING GIST (location)');

        Schema::create('provider_service', function (Blueprint $table) {
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->primary(['provider_id', 'service_id']);
        });

        Schema::create('provider_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
        });

        // Une plage par jour (1 = lundi … 7 = dimanche). Si closes_at < opens_at,
        // la plage passe minuit (ex. service de nuit 18:00 → 02:00).
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->unique(['provider_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('provider_photos');
        Schema::dropIfExists('provider_service');
        Schema::dropIfExists('providers');
    }
};
