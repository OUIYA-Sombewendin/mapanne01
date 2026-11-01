<?php

namespace App\Support;

use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Notifications\AnonymousNotifiable;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * Canaux d'envoi des notifications instantanées, selon les réglages de l'admin :
 * push (gratuit, activé par défaut), SMS et WhatsApp (payants, désactivés tant qu'ils ne sont pas configurés).
 */
class Channels
{
    public static function for(object $notifiable): array
    {
        $channels = [];

        if (self::pushReady() && ! $notifiable instanceof AnonymousNotifiable && method_exists($notifiable, 'pushSubscriptions') && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }
        if (self::smsReady() && filled($notifiable->routeNotificationFor('sms'))) {
            $channels[] = SmsChannel::class;
        }
        if (self::whatsappReady() && filled($notifiable->routeNotificationFor('whatsapp'))) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }

    public static function pushReady(): bool
    {
        return feature('push_enabled') && filled(config('webpush.vapid.public_key')) && filled(config('webpush.vapid.private_key'));
    }

    public static function smsReady(): bool
    {
        return feature('sms_enabled') && (setting('sms_driver') === 'log'
            || (filled(setting('sms_client_id')) && filled(setting('sms_client_secret')) && filled(setting('sms_sender'))));
    }

    public static function whatsappReady(): bool
    {
        return feature('whatsapp_enabled') && filled(setting('whatsapp_phone_id')) && filled(setting('whatsapp_token'));
    }
}
