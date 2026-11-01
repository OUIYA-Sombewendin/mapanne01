<?php

namespace App\Support;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

/**
 * Envoi de notifications « au mieux » : respecte l'option correspondante de l'admin
 * et n'interrompt jamais l'action de l'utilisateur si le serveur d'emails est en panne
 * (l'erreur est journalisée dans storage/logs).
 * Renvoie true seulement si l'email est réellement parti (voir Delivery::mailReady) :
 * les contrôleurs n'annoncent « envoyé » que dans ce cas.
 */
class Notifier
{
    public static function send(object $notifiable, Notification $notification, ?string $feature = null): bool
    {
        if ($feature && ! feature($feature)) {
            return false;
        }
        if (method_exists($notifiable, 'routeNotificationFor') && blank($notifiable->routeNotificationFor('mail', $notification))) {
            return false;
        }

        try {
            $notifiable->notify($notification);
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        return Delivery::mailReady();
    }

    public static function sendTo(?string $email, Notification $notification, ?string $feature = null): bool
    {
        if (blank($email) || ($feature && ! feature($feature))) {
            return false;
        }

        try {
            NotificationFacade::route('mail', $email)->notify($notification);
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        return Delivery::mailReady();
    }
}
