<?php

namespace App\Notifications;

use App\Models\AssistRequest;
use App\Support\Channels;
use App\Support\Phone;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/** Informe le conducteur de l'avancement de sa demande : acceptée, prestataire en route, terminée, sans réponse. */
class AssistUpdate extends Notification
{
    public function __construct(public AssistRequest $request) {}

    public function via(object $notifiable): array
    {
        return Channels::for($notifiable);
    }

    public function text(): string
    {
        $provider = $this->request->provider;

        return match ($this->request->status) {
            'accepted' => $provider->business_name.' a accepté votre demande. Téléphone : '.Phone::format($provider->phone).'.',
            'on_way' => $provider->business_name.' est en route vers vous.',
            'done' => 'Intervention terminée. Merci de laisser un avis sur '.$provider->business_name.'.',
            'expired' => 'Aucun prestataire n\'a pu répondre à temps. Appelez directement un dépanneur proche depuis '.setting('site_name').'.',
            default => 'Votre demande : '.$this->request->statusLabel().'.',
        };
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('🆘 '.$this->request->statusLabel())
            ->body($this->text())
            ->icon(asset('favicon.svg'))
            ->tag('assist-'.$this->request->id)
            ->data(['url' => route('assist.show', $this->request)]);
    }

    public function toSms(object $notifiable): string
    {
        return setting('site_name').' : '.$this->text().' Suivi : '.route('assist.show', $this->request);
    }

    public function toWhatsApp(object $notifiable): string
    {
        return $this->toSms($notifiable);
    }
}
