<?php

namespace STS\Notifications;

use  STS\Services\Notifications\BaseNotification;
use  STS\Services\Notifications\Channels\MailChannel;
use  STS\Services\Notifications\Channels\DatabaseChannel;

class CancelPassengerNotification extends BaseNotification
{
    protected $via = [DatabaseChannel::class, MailChannel::class];

    public function toEmail($user)
    {
        $trip = $this->getAttribute('trip');
        $from = $this->getAttribute('from');

        $isDriver = $trip->user_id == $from->id;

        $title = $isDriver ? $from->name.' te ha bajado del viaje' : $from->name.' se ha bajado del viaje';

        return [
            'title' => $title,
            'email_view' => 'passenger_email',
            'type' => 'cancel',
            'is_driver' => $isDriver,
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
            'trip_id' => $this->getAttribute('trip')->id
        ];
    }
}
