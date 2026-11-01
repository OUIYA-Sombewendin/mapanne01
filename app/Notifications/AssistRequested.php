<?php

namespace App\Notifications;

use App\Models\AssistRequest;
use App\Support\Channels;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/** Prévient un prestataire qu'un conducteur en panne près de lui demande de l'aide. */
class AssistRequested extends Notification
{
    public function __construct(public AssistRequest $request, public ?int $distanceM = null) {}

    public function via(object $notifiable): array
    {
        return Channels::for($notifiable);
    }

    private function text(): string
    {
        $distance = $this->distanceM !== null ? ' à '.($this->distanceM < 1000 ? $this->distanceM.' m' : round($this->distanceM / 1000, 1).' km') : '';

        return 'Dépannage demandé'.$distance.' : '.$this->request->problemLabel().' ('.$this->request->vehicleLabel().').';
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('🆘 '.$this->request->problemIcon().' Conducteur en panne')
            ->body($this->text().' Le premier qui accepte intervient.')
            ->icon(asset('favicon.svg'))
            ->tag('assist-'.$this->request->id)
            ->data(['url' => route('pro.assist.index')])
            ->options(['TTL' => 1800, 'urgency' => 'high']);
    }

    public function toSms(object $notifiable): string
    {
        return setting('site_name').' : '.$this->text().' Répondez vite : '.route('pro.assist.index');
    }

    public function toWhatsApp(object $notifiable): string
    {
        return $this->toSms($notifiable);
    }
}
