<?php

namespace App\Notifications\Channels;

use App\Support\Phone;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

/**
 * Messages WhatsApp via l'API WhatsApp Business de Meta (désactivé par défaut : Réglages › Notifications).
 * La notification fournit toWhatsApp($notifiable) : string ; le destinataire vient de routeNotificationFor('whatsapp').
 */
class WhatsAppChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $phone = Phone::normalize((string) $notifiable->routeNotificationFor('whatsapp', $notification));
        if (! $phone || ! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        Http::withToken((string) setting('whatsapp_token'))
            ->post('https://graph.facebook.com/v20.0/'.setting('whatsapp_phone_id').'/messages', [
                'messaging_product' => 'whatsapp',
                'to' => ltrim($phone, '+'),
                'type' => 'text',
                'text' => ['body' => $notification->toWhatsApp($notifiable)],
            ])->throw();
    }
}
