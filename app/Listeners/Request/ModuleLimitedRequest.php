<?php

namespace STS\Listeners\Request;

use STS\Events\Passenger\Accept as AcceptEvent;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use STS\Events\Passenger\AutoCancel as AutoCancelEvent;

class ModuleLimitedRequest
{
    /**
     * Create the event listener.
     *
     * @return void
     */

    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param  AcceptEvent  $event
     * @return void
     */
    public function handle(AcceptEvent $event)
    {
        $trip = $event->trip;
        $acceptedUser = $event->to;

        $module_user_request_limited_enabled = config('carpoolear.module_user_request_limited_enabled', false);

        if ($module_user_request_limited_enabled) {
            $hours_range = (int) config('carpoolear.module_user_request_limited_hours_range', 2);
            
            $requests = $acceptedUser->pendingRequests($hours_range, $trip->trip_date)
            ->where('trip_id', '<>', $trip->id)
            ->with('trip')  // Eager load the trip relation
            ->get()
            ->filter(function($request) use ($trip) {
                // Check if destination is the same or similar
                return $request->trip && $request->trip->to_town === $trip->to_town;
            });

            if ($requests->isNotEmpty()) {
                foreach ($requests as $request) {
                    $request->request_state = Passenger::STATE_CANCELED;
                    $request->canceled_state = Passenger::CANCELED_SYSTEM;
                    $tripRequest = Trip::find($request->trip_id);
                    if ($tripRequest) {
                        $tripOwnerUser = User::find($tripRequest->user_id);
                        event(new AutoCancelEvent($tripRequest, $tripOwnerUser, $acceptedUser));
                    }
                    $request->save();
                }
            }
        }
    }
}