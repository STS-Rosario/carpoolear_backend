<?php

namespace STS\Contracts\Repository;

interface IRatingRepository
{
    public function getRating($id);

    public function getRatings($user, $data);
    
    public function getPendingRatings($user);
    
    public function rateUser ($user_from, $user_to, $trip, $value, $comment);

    public function replyRating ($rating, $user, $comment);

}