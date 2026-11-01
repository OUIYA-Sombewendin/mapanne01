<?php

namespace App\Notifications;

use App\Enums\ProviderStatus;
use App\Models\Provider;
use App\Support\Channels;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/** Validation, refus, suspension ou réactivation d'une fiche prestataire : par email, dans l'espace pro et en push. */
class ProviderDecision extends Notification
{
    public function __construct(public Provider $provider) {}

    public function via(object $notifiable): array
    {
        // La notification dans l'espace d'abord : un serveur d'emails en panne ne doit pas l'empêcher.
        return array_merge(['database', 'mail'], Channels::for($notifiable));
    }

    public function title(): string
    {
        return match ($this->provider->status) {
            ProviderStatus::Approved => 'Fiche validée : vous êtes en ligne',
            ProviderStatus::Rejected => 'Fiche à corriger',
            ProviderStatus::Suspended => 'Fiche suspendue',
            default => 'Fiche en cours de vérification',
        };
    }

    public function text(): string
    {
        $p = $this->provider;

        return match ($p->status) {
            ProviderStatus::Approved => "« {$p->business_name} » a été vérifiée et apparaît maintenant dans les recherches.",
            ProviderStatus::Rejected => "« {$p->business_name} » n'a pas pu être validée. Motif : {$p->rejection_reason}",
            ProviderStatus::Suspended => "« {$p->business_name} » n'apparaît plus dans les recherches. Motif : {$p->rejection_reason}",
            default => "« {$p->business_name} » sera vérifiée par notre équipe.",
        };
    }

    public function toArray(object $notifiable): array
    {
        $icon = match ($this->provider->status) {
            ProviderStatus::Approved => '🎉',
            ProviderStatus::Rejected => '✋',
            ProviderStatus::Suspended => '⛔',
            default => '⏳',
        };

        return ['icon' => $icon, 'title' => $this->title(), 'body' => $this->text(), 'url' => route('pro.dashboard'), 'provider_id' => $this->provider->id];
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return (new WebPushMessage)->title($this->title())->body($this->text())->icon(asset('favicon.svg'))->data(['url' => route('pro.dashboard')]);
    }

    public function toSms(object $notifiable): string
    {
        return setting('site_name').' : '.$this->title().'. '.$this->text();
    }

    public function toWhatsApp(object $notifiable): string
    {
        return $this->toSms($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->provider;
        $mail = (new MailMessage)->greeting('Bonjour '.$notifiable->name.',');

        return match ($p->status) {
            ProviderStatus::Approved => $mail
                ->subject('Votre fiche MaPanne est en ligne')
                ->line("Bonne nouvelle : « {$p->business_name} » a été vérifiée et apparaît maintenant dans les recherches.")
                ->line('Pensez à garder vos horaires et votre disponibilité à jour.')
                ->action('Voir ma fiche', route('providers.show', $p)),
            ProviderStatus::Rejected => $mail
                ->subject('Votre fiche MaPanne doit être corrigée')
                ->line("Votre fiche « {$p->business_name} » n'a pas pu être validée.")
                ->line('Motif : '.$p->rejection_reason)
                ->line('Corrigez-la puis renvoyez-la en vérification depuis votre espace pro.')
                ->action('Ouvrir mon espace pro', route('pro.dashboard')),
            ProviderStatus::Suspended => $mail
                ->subject('Votre fiche MaPanne est suspendue')
                ->line("Votre fiche « {$p->business_name} » n'apparaît plus dans les recherches.")
                ->line('Motif : '.$p->rejection_reason)
                ->line('Contactez le support MaPanne pour en savoir plus : '.setting('support_email')),
            default => $mail
                ->subject('Votre fiche MaPanne est en cours de vérification')
                ->line("Votre fiche « {$p->business_name} » sera vérifiée par notre équipe."),
        };
    }
}
