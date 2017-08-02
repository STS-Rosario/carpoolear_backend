<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\PushChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;
use  STS\Services\Notifications\Channels\FacebookChannel;

class CancelPassengerNotification extends BaseNotification
{
    protected $via = [DatabaseChannel::class, MailChannel::class, PushChannel::class, FacebookChannel::class];

    public function toEmail($user)
    {
        $trip = $this->getAttribute('trip');
        $from = $this->getAttribute('from');

        $isDriver = $trip->user_id == $from->id;

        $title = $isDriver ? $from->name.' te ha bajado del viaje' : $from->name.' se ha bajado del viaje';
        $reasonMessage = $isDriver ? 'te ha bajado del viaje' : 'se ha bajado del viaje';

        return [
            'title' => $title,
            'email_view' => 'passenger_out_email',
            'type' => 'cancel',
            'is_driver' => $isDriver,
            'reason_message' => $reasonMessage,
            'url' => config('app.url').'/app/trips/'.$this->getAttribute('trip')->id
        ];
    }

    public function toString()
    {
        $trip = $this->getAttribute('trip');
        $from = $this->getAttribute('from');

        $isDriver = $trip->user_id == $from->id;

        $title = $isDriver ? $from->name.' te ha bajado del viaje' : $from->name.' se ha bajado del viaje';

        return $title;
    }

    public function getExtras()
    {
        return [
            'type' => 'trip',
            'trip_id' => $this->getAttribute('trip')->id,
        ];
    }

    public function toPush($user, $device)
    {
        $trip = $this->getAttribute('trip');
        $from = $this->getAttribute('from');
        $isDriver = $trip->user_id == $from->id;
        $title = $isDriver ? $from->name.' te ha bajado del viaje' : $from->name.' se ha bajado del viaje';

        return [
            'message' => $title,
            'url' => 'passenger',
            'extras' => [
                'id' => $trip->id,
            ],
        ];
    }
}
