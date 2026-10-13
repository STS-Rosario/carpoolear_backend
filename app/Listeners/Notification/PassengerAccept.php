<?php

namespace STS\Listeners\Notification;

use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Events\Passenger\Accept;
use STS\Notifications\AcceptPassengerNotification;
use STS\Services\TripGroupChatService;

class PassengerAccept implements ShouldQueue
{
    public function __construct(private TripGroupChatService $tripGroupChatService) {}

    /**
     * Handle the event.
     *
     * Listener execution order is not guaranteed, so make sure the trip group chat
     * exists (and contains the accepted user) before attaching it to the notification.
     *
     * @return void
     */
    public function handle(Accept $event)
    {
        $trip = $event->trip;
        $from = $event->from;
        $to = $event->to;

        if (! $to) {
            return;
        }

        $this->tripGroupChatService->syncOnPassengerAccept($trip, $to);

        // Reload so the freshly created/updated conversation is visible.
        $trip = $trip->fresh();

        $notification = new AcceptPassengerNotification;
        $notification->setAttribute('trip', $trip);
        $notification->setAttribute('from', $from);
        $notification->setAttribute('token', $to);

        $conversation = $trip->conversation;
        if ($conversation) {
            $notification->setAttribute('conversation', $conversation->fresh());
        }

        $notification->notify($to);
    }
}
