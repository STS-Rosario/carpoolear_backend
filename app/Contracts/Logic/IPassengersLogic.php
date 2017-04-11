<?php

namespace STS\Contracts\Logic;

interface IPassengersLogic
{
    public function getPassengers($tripId, $user, $data);

    public function getPendingRequests($tripId, $user, $data);

    public function newRequest($tripId, $user, $data);

    public function cancelRequest($tripId, $user, $data);

    public function acceptRequest($tripId, $acceptedUserId, $user, $data);

    public function rejectRequest($tripId, $rejectedUserId, $user, $data);

    public function isUserRequestAccepted($tripId, $userId);

    public function isUserRequestRejected($tripId, $userId);

    public function isUserRequestPending($tripId, $userId);
}
