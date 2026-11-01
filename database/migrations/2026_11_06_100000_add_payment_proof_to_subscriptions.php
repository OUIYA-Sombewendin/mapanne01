<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paiement des abonnements par Orange Money ou Moov Money, hors plateforme :
 * le prestataire envoie la capture, le numéro utilisé et la référence ; l'admin vérifie et active.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('payment_operator', 20)->nullable()->after('payment_reference'); // orange, moov
            $table->string('payer_phone', 20)->nullable()->after('payment_operator');
            $table->string('proof_path')->nullable()->after('payer_phone');                 // disque privé
            $table->timestamp('proof_submitted_at')->nullable()->after('proof_path');
            $table->string('rejected_reason', 255)->nullable()->after('notes');
            $table->timestamp('reviewed_at')->nullable()->after('rejected_reason');
        });

        // Une même référence de transaction ne peut servir qu'une fois (hors demandes annulées ou refusées).
        DB::statement("CREATE UNIQUE INDEX subscriptions_payment_reference_unique ON subscriptions (payment_operator, upper(payment_reference))
            WHERE payment_operator IS NOT NULL AND payment_reference IS NOT NULL AND status IN ('pending', 'active', 'expired')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS subscriptions_payment_reference_unique');
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['payment_operator', 'payer_phone', 'proof_path', 'proof_submitted_at', 'rejected_reason', 'reviewed_at']);
        });
    }
};
