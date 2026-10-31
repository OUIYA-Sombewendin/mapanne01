<?php

namespace App\Support;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

/**
 * Envoi de notifications « au mieux » : respecte l'option correspondante de l'admin
 * et n'interrompt jamais l'action de l'utilisateur si le serveur d'emails est en panne
 * (l'erreur est journalisée dans storage/logs).
 */
class Notifier
{
    public static function send(object $notifiable, Notification $notification, ?string $feature = null): void
    {
        if ($feature && ! feature($feature)) {
            return;
        }
        if (method_exists($notifiable, 'routeNotificationFor') && blank($notifiable->routeNotificationFor('mail', $notification))) {
            return;
        }

        try {
            $notifiable->notify($notification);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public static function sendTo(?string $email, Notification $notification, ?string $feature = null): void
    {
        if (blank($email) || ($feature && ! feature($feature))) {
            return;
        }

        try {
            NotificationFacade::route('mail', $email)->notify($notification);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
