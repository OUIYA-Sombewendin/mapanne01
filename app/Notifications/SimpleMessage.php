<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification email générique (sujet, lignes, bouton) pour les messages courts :
 * nouvel avis, nouvelle offre, produit masqué, signalement…
 */
class SimpleMessage extends Notification
{
    /** @param list<string> $lines */
    public function __construct(
        public string $subject,
        public array $lines,
        public ?string $actionText = null,
        public ?string $actionUrl = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subject);

        if (isset($notifiable->name)) {
            $mail->greeting('Bonjour '.$notifiable->name.',');
        }
        foreach ($this->lines as $line) {
            $mail->line($line);
        }
        if ($this->actionText && $this->actionUrl) {
            $mail->action($this->actionText, $this->actionUrl);
        }

        return $mail;
    }
}
