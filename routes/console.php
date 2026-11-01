<?php

use App\Models\ActivityLog;
use App\Models\Subscription;
use App\Notifications\SimpleMessage;
use App\Support\Backup;
use App\Support\Notifier;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
 * Tâches planifiées. Sur le serveur, une seule ligne cron suffit :
 *   * * * * * cd /chemin/mapanne && php artisan schedule:run >> /dev/null 2>&1
 */

// Abonnements : clôture des échus (retour à l'offre par défaut) et rappel avant l'échéance.
Artisan::command('mapanne:subscriptions', function () {
    $expired = 0;
    Subscription::where('status', 'active')->whereNotNull('ends_at')->where('ends_at', '<=', now())->with('provider.user', 'plan')
        ->each(function (Subscription $s) use (&$expired) {
            $s->update(['status' => 'expired']);
            $s->provider->syncPlanBenefits();
            ActivityLog::record('subscription.expired', $s, ['offre' => $s->plan->name]);
            Notifier::send($s->provider->user, new SimpleMessage(
                'Votre offre '.$s->plan->name.' est arrivée à échéance',
                ['Votre compte est repassé sur l\'offre par défaut. Renouvelez votre abonnement pour retrouver toutes vos fonctionnalités.'],
                'Renouveler', route('pro.subscription'),
            ));
            $expired++;
        });

    $days = (int) setting('subscription_reminder_days');
    $reminded = 0;
    if ($days > 0) {
        Subscription::current()->whereNotNull('ends_at')
            ->whereBetween('ends_at', [now()->addDays($days)->startOfDay(), now()->addDays($days)->endOfDay()])
            ->with('provider.user', 'plan')
            ->each(function (Subscription $s) use (&$reminded, $days) {
                Notifier::send($s->provider->user, new SimpleMessage(
                    'Votre offre '.$s->plan->name.' expire dans '.$days.' jours',
                    ['Échéance le '.$s->ends_at->format('d/m/Y').'.', setting('subscription_payment_instructions')],
                    'Renouveler', route('pro.subscription'),
                ));
                $reminded++;
            });
    }

    $this->info("$expired abonnement(s) expiré(s), $reminded rappel(s) envoyé(s).");
})->purpose('Clôture les abonnements échus et envoie les rappels');

// Sauvegarde automatique (si activée dans Admin › Réglages › Sauvegardes).
Artisan::command('mapanne:backup {--force : Sauvegarder même si l\'option automatique est désactivée}', function () {
    if (! $this->option('force') && ! feature('backup_auto_enabled')) {
        $this->line('Sauvegarde automatique désactivée.');

        return;
    }
    $name = Backup::create();
    ActivityLog::record('backup.created', null, ['fichier' => $name, 'automatique' => true]);
    $this->info("Sauvegarde créée : $name");
})->purpose('Crée une sauvegarde complète (base + fichiers)');

Artisan::command('mapanne:assist-expire', function () {
    $this->info(\App\Support\AssistDispatch::expireStale().' demande(s) de dépannage fermée(s) sans réponse.');
})->purpose('Ferme les demandes de dépannage restées sans réponse');

// Carnet d'entretien : un seul message par client, une seule fois par échéance.
Artisan::command('mapanne:maintenance-reminders', function () {
    if (! feature('logbook_enabled') || ! feature('logbook_reminders_enabled')) {
        $this->info('Rappels d\'entretien désactivés.');

        return;
    }
    $sent = 0;
    \App\Models\VehicleReminder::query()
        ->where('next_due_on', '<=', today()->addDays(\App\Models\VehicleReminder::NOTICE_DAYS))
        ->where(fn ($q) => $q->whereNull('notified_for')->orWhereColumn('notified_for', '!=', 'next_due_on'))
        ->with('vehicle.user')
        ->get()
        ->groupBy(fn ($r) => $r->vehicle->user_id)
        ->each(function ($reminders) use (&$sent) {
            Notifier::send($reminders->first()->vehicle->user, new \App\Notifications\MaintenanceDue($reminders->sortBy('next_due_on')->values()));
            \App\Models\VehicleReminder::whereKey($reminders->modelKeys())->update(['notified_for' => \Illuminate\Support\Facades\DB::raw('next_due_on')]);
            $sent++;
        });
    $this->info("$sent client(s) prévenu(s).");
})->purpose('Envoie les rappels d\'entretien arrivant à échéance');

Schedule::command('mapanne:subscriptions')->dailyAt('06:00');
Schedule::command('mapanne:maintenance-reminders')->dailyAt('07:30');
Schedule::command('mapanne:assist-expire')->everyFiveMinutes();
Schedule::command('mapanne:backup')->dailyAt('02:00');
