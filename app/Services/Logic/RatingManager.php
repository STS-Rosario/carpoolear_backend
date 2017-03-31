<?php

namespace STS\Services\Logic;

use Validator;

use STS\User;
use STS\Entities\Passanger;
use STS\Contracts\Logic\IRatingLogic;
use STS\Contracts\Repository\IRatingRepository;

class RatingManager extends BaseManager implements IRatingLogic
{
    protected $ratingRepository;
    
    public function __construct(IPassengerRepository $ratingRepository)
    {
        $this->ratingRepository = $ratingRepository;
    }

    public function validator(array $data)
    {
        return Validator::make($data, [
            'trip_id' => 'required|numeric',
            'user_id_from' => 'required|numeric',
            'user_id_to' => 'required|numeric',
            'rating' => 'required|integer|in:0,1,',
        ]);
    }

    public function getRating($id)
    {
        return $this->ratingRepository->getRating($id);
    }
    
    public function getRatings ($user, $data) 
    {
        return $this->ratingRepository->getRatings($user, $data);
    }

    public function getPendingRatings ($user) 
    {
        return $this->ratingRepository->getPendingRatings($user);

    }

    public function rateUser ($user_from, $user_to, $trip, $value, $comment)
    {
        $input = [
            'trip_id' => $trip->id,
            'user_id_from' => $user_from->id,
            'user_id_to' => $user_to->id,
            'rating' => $value
        ];
        $v = $this->validator($input, $user->id);

        if ($v->fails()) {
            $this->setErrors($v->errors());

            return;
        } else {
            //TODO: Validar si realmente puedo o debo calificar

            return $this->ratingRepository->rateUser($user_from, $user_to, $trip, $value, $comment);
        }

        
    }


    public function replyRating ($rating_id, $comment)
    {
        //TODO: validar si puedo contestar
        $rating = $this->getRating($rating_id);

        return $this->ratingRepository->replyRating($rating, $comment);
    }

    
    
 }