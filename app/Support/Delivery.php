<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * État réel des envois (email, push, SMS, WhatsApp, file d'attente).
 * Sert à ne jamais annoncer « envoyé » quand rien ne part, et à l'écran « État des envois » de l'admin.
 */
class Delivery
{
    /** Modes d'envoi qui n'envoient rien à personne (écriture dans le journal, mémoire). */
    private const SILENT_MAILERS = ['log', 'array'];

    public static function mailer(): string
    {
        MailConfigurator::apply();

        return (string) config('mail.default');
    }

    /** Les emails partent-ils vraiment vers les boîtes des destinataires ? */
    public static function mailReady(): bool
    {
        $mailer = self::mailer();
        if (in_array($mailer, self::SILENT_MAILERS, true)) {
            return false;
        }
        if (config("mail.mailers.$mailer.transport") === 'smtp') {
            return filled(config("mail.mailers.$mailer.host"));
        }

        return true;
    }

    /** Message affiché à l'utilisateur quand un email n'a pas pu partir. */
    public static function mailFailedMessage(string $what = 'L\'email'): string
    {
        return $what.' n\'a pas pu être envoyé : l\'envoi d\'emails n\'est pas encore en service. Réessayez plus tard ou contactez le support ('.setting('support_email').').';
    }

    /** Demandes d'envoi en attente dans la file (null si la table n'existe pas). */
    public static function queue(): array
    {
        try {
            $pending = DB::table('jobs')->count();
            $oldest = DB::table('jobs')->min('created_at');
            $failed = DB::table('failed_jobs')->count();
        } catch (Throwable) {
            return ['driver' => config('queue.default'), 'pending' => null, 'failed' => null, 'stuck' => false];
        }

        return [
            'driver' => config('queue.default'),
            'pending' => $pending,
            'failed' => $failed,
            // Une tâche attend depuis plus de 5 minutes : le programme de traitement (queue:work) ne tourne pas.
            'stuck' => $oldest && now()->timestamp - (int) $oldest > 300,
        ];
    }

    /** Lignes de l'écran « État des envois » : [canal, actif ?, détail]. */
    public static function status(): array
    {
        $mailer = self::mailer();
        $mailDetail = match (true) {
            self::mailReady() => 'Mode « '.$mailer.' » : les emails partent. Faites un envoi de test pour le confirmer.',
            in_array($mailer, self::SILENT_MAILERS, true) => 'Mode « '.$mailer.' » : les emails sont seulement écrits dans le journal du serveur (storage/logs), personne ne les reçoit. Choisissez « SMTP » ci-dessus et saisissez les accès de votre fournisseur (Gmail, Brevo, OVH…).',
            default => 'SMTP choisi mais le serveur n\'est pas renseigné.',
        };

        $queue = self::queue();
        $queueOk = $queue['driver'] === 'sync' || ($queue['pending'] !== null && ! $queue['stuck']);
        $queueDetail = $queue['driver'] === 'sync'
            ? 'Envois faits immédiatement (pas de file d\'attente).'
            : ($queue['pending'] === null ? 'File « '.$queue['driver'].' ».'
                : $queue['pending'].' envoi(s) en attente, '.$queue['failed'].' en échec.'
                    .($queue['stuck'] ? ' Des envois attendent depuis plus de 5 minutes : lancez « php artisan queue:work » sur le serveur.' : ''));

        return [
            ['📧 Emails', self::mailReady(), $mailDetail],
            ['🔔 Notifications push', Channels::pushReady(), Channels::pushReady() ? 'Actives (gratuites).' : (feature('push_enabled') ? 'Clés VAPID manquantes dans le .env (php artisan webpush:vapid).' : 'Désactivées dans Réglages.')],
            ['💬 SMS', Channels::smsReady() && setting('sms_driver') !== 'log', Channels::smsReady() ? (setting('sms_driver') === 'log' ? 'Mode test : écrits dans le journal, non envoyés.' : 'Actifs.') : 'Désactivés ou non configurés (Réglages › SMS).'],
            ['🟢 WhatsApp', Channels::whatsappReady(), Channels::whatsappReady() ? 'Actif.' : 'Désactivé ou non configuré (Réglages › WhatsApp).'],
            ['⏳ File d\'attente', $queueOk, $queueDetail],
        ];
    }
}
