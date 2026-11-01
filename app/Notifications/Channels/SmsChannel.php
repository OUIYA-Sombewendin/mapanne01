<?php

namespace App\Notifications\Channels;

use App\Support\Phone;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Envoi de SMS (désactivé par défaut : Réglages › Notifications).
 * Fournisseurs : « orange » (Orange SMS API) ou « log » (écrit dans le journal, pour tester).
 * La notification fournit toSms($notifiable) : string ; le destinataire vient de routeNotificationFor('sms').
 */
class SmsChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $phone = Phone::normalize((string) $notifiable->routeNotificationFor('sms', $notification));
        if (! $phone || ! method_exists($notification, 'toSms')) {
            return;
        }
        $text = $notification->toSms($notifiable);

        match (setting('sms_driver')) {
            'orange' => $this->orange($phone, $text),
            default => Log::info('SMS à '.$phone.' : '.$text),
        };
    }

    private function orange(string $phone, string $text): void
    {
        $token = Cache::remember('sms.orange.token', now()->addMinutes(50), function () {
            $response = Http::asForm()->withBasicAuth((string) setting('sms_client_id'), (string) setting('sms_client_secret'))
                ->post('https://api.orange.com/oauth/v3/token', ['grant_type' => 'client_credentials']);

            return $response->throw()->json('access_token') ?? throw new RuntimeException('Orange SMS : jeton absent.');
        });

        $sender = 'tel:'.(Phone::normalize((string) setting('sms_sender')) ?? setting('sms_sender'));
        Http::withToken($token)->post('https://api.orange.com/smsmessaging/v1/outbound/'.rawurlencode($sender).'/requests', [
            'outboundSMSMessageRequest' => [
                'address' => 'tel:'.$phone,
                'senderAddress' => $sender,
                'outboundSMSTextMessage' => ['message' => $text],
            ],
        ])->throw();
    }
}
