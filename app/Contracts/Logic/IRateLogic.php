<?php

namespace STS\Contracts\Logic;

interface IRateLogic
{
    public function getRating($id);

    public function getRatings($user, $data);

    public function rateUser ($user_from, $user_to, $trip, $value, $comment);

    public function getPendingRatings ($user);

    public function replyRating ($rating, $comment);


}