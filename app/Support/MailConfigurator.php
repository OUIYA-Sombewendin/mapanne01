<?php

namespace App\Support;

use Throwable;

/**
 * Applique la configuration d'envoi d'emails saisie dans l'administration
 * (Réglages › Serveur d'envoi d'emails). « env » = on garde le fichier .env.
 */
class MailConfigurator
{
    public static function apply(): void
    {
        try {
            $mode = setting('mail_mailer', 'env');

            if ($mode === 'smtp' && filled(setting('mail_host'))) {
                $encryption = setting('mail_encryption');
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => setting('mail_host'),
                    'mail.mailers.smtp.port' => (int) setting('mail_port'),
                    'mail.mailers.smtp.username' => setting('mail_username'),
                    'mail.mailers.smtp.password' => setting('mail_password'),
                    'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : null,
                ]);
            } elseif ($mode === 'log') {
                config(['mail.default' => 'log']);
            }

            if (filled(setting('mail_from_address'))) {
                config(['mail.from.address' => setting('mail_from_address')]);
            }
            if (filled(setting('mail_from_name'))) {
                config(['mail.from.name' => setting('mail_from_name')]);
            }
        } catch (Throwable) {
            // Base indisponible (installation) : on garde la configuration .env.
        }
    }
}
