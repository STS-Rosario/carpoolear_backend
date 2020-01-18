<?php

namespace STS\Listeners\Request;

use STS\Events\Passenger\Accept as AcceptEvent;
use STS\Entities\Passenger;
use STS\Entities\Trip;
use STS\User;
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

        $module_user_request_limited = config('carpoolear.module_user_request_limited', false);

        if ($module_user_request_limited && $module_user_request_limited->enabled) {
            $hours_range = $module_user_request_limited->hours_range;
            $requests = $acceptedUser->pendingRequests($hours_range, $trip->trip_date)->where('trip_id', '<>', $trip->id)->get();
            if ($requests && count($requests) > 0) {
                foreach ($requests as $request) {
                    $request->request_state = Passenger::STATE_CANCELED;
                    $request->canceled_state = Passenger::CANCELED_SYSTEM;
                    $tripRequest = Trip::find($request->trip_id);
                    $tripOwnerUser = User::find($tripRequest->user_id);
                    event(new AutoCancelEvent($tripRequest, $tripOwnerUser, $acceptedUser));
                    $request->save();
                }
            }
        }
    }
}