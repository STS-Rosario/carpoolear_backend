<?php

namespace STS\Contracts\Logic;

interface IRateLogic
{
    public function getRate($userOrHash, $user_to_id, $trip_id);

    public function getRatings($user, $data = []);

    public function getPendingRatings($user);

    public function getPendingRatingsByHash($hash) ;

    public function rateUser($user_from, $user_to_id, $trip_id, $data);
    
    public function replyRating($user_from, $user_to_id, $trip_id, $comment);

    public function activeRatings($when);
}
