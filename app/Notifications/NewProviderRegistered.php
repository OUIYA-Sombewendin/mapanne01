<?php

namespace App\Notifications;

use App\Models\Provider;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewProviderRegistered extends Notification
{
    public function __construct(public Provider $provider) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nouvelle inscription professionnelle : '.$this->provider->business_name)
            ->line("{$this->provider->business_name} ({$this->provider->type->label()}) vient de s'inscrire et attend votre validation.")
            ->action('Examiner la fiche', route('admin.providers.show', $this->provider));
    }
}
