<?php

namespace App\Notifications;

use App\Enums\ProviderStatus;
use App\Models\Provider;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Validation, refus, suspension ou réactivation d'une fiche prestataire. */
class ProviderDecision extends Notification
{
    public function __construct(public Provider $provider) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
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
