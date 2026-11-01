<?php

namespace App\Notifications;

use App\Models\Provider;
use App\Support\Channels;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/** Réponse de l'équipe à une demande de modification d'une fiche validée (nom, téléphone, position…). */
class ProviderChangesReviewed extends Notification
{
    /** @param  list<string>  $fields  libellés des champs concernés */
    public function __construct(public Provider $provider, public bool $applied, public array $fields, public ?string $reason = null) {}

    public function via(object $notifiable): array
    {
        // La notification dans l'espace d'abord : un serveur d'emails en panne ne doit pas l'empêcher.
        return array_merge(['database', 'mail'], Channels::for($notifiable));
    }

    public function title(): string
    {
        return $this->applied ? 'Modification acceptée' : 'Modification refusée';
    }

    public function text(): string
    {
        $fields = implode(', ', $this->fields);

        return $this->applied
            ? "Votre demande de modification ({$fields}) de « {$this->provider->business_name} » a été appliquée."
            : "Votre demande de modification ({$fields}) de « {$this->provider->business_name} » n'a pas été acceptée.".($this->reason ? ' Motif : '.$this->reason : '');
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title().' · '.setting('site_name'))
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line($this->text())
            ->action('Ouvrir ma fiche', route('pro.profile'));
    }

    public function toArray(object $notifiable): array
    {
        return ['icon' => $this->applied ? '✅' : '✋', 'title' => $this->title(), 'body' => $this->text(), 'url' => route('pro.profile'), 'provider_id' => $this->provider->id];
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return (new WebPushMessage)->title($this->title())->body($this->text())->icon(asset('favicon.svg'))->data(['url' => route('pro.profile')]);
    }

    public function toSms(object $notifiable): string
    {
        return setting('site_name').' : '.$this->text();
    }

    public function toWhatsApp(object $notifiable): string
    {
        return $this->toSms($notifiable);
    }
}
