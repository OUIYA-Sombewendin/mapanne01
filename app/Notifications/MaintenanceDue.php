<?php

namespace App\Notifications;

use App\Models\VehicleReminder;
use App\Support\Channels;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use NotificationChannels\WebPush\WebPushMessage;

/** Rappel d'entretien : vidange, assurance, visite technique… arrivant à échéance (un message par client). */
class MaintenanceDue extends Notification
{
    /** @param Collection<int, VehicleReminder> $reminders (avec vehicle) */
    public function __construct(public Collection $reminders) {}

    public function via(object $notifiable): array
    {
        return array_merge(['mail'], Channels::for($notifiable));
    }

    /** @return list<string> */
    public function lines(): array
    {
        return $this->reminders->map(fn (VehicleReminder $r) => $r->icon().' '.$r->label.' — '.$r->vehicle->title().' : '.mb_strtolower($r->dueLabel()))->all();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->reminders->count() > 1 ? $this->reminders->count().' entretiens à prévoir' : 'Entretien à prévoir : '.$this->reminders->first()->label)
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Votre carnet d\'entretien vous rappelle :');
        foreach ($this->lines() as $line) {
            $mail->line($line);
        }

        return $mail->line('Une fois fait, notez-le dans le carnet : le prochain rappel se calcule tout seul.')
            ->action('Ouvrir mon carnet', route('logbook.index'));
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('🛠️ Entretien à prévoir')
            ->body(implode("\n", $this->lines()))
            ->icon(asset('favicon.svg'))
            ->tag('maintenance')
            ->data(['url' => route('logbook.index')]);
    }

    public function toSms(object $notifiable): string
    {
        return setting('site_name').' : '.implode(' ; ', $this->lines()).'. '.route('logbook.index');
    }

    public function toWhatsApp(object $notifiable): string
    {
        return $this->toSms($notifiable);
    }
}
