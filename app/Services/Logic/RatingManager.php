<?php

namespace STS\Services\Logic;

use Validator;

use Carbon\Carbon;
use STS\User;
use STS\Entities\Passenger;
use STS\Entities\Rating;
use STS\Contracts\Logic\IRateLogic;
use STS\Contracts\Repository\IRatingRepository;
use STS\Contracts\Repository\Trip as TripRepo;
use STS\Events\Rating\PendingRate as PendingEvent;
use Illuminate\Database\Eloquent\Collection;

class RatingManager extends BaseManager implements IRateLogic
{
    protected $ratingRepository;

    protected $tripRepo;
    
    public function __construct(IRatingRepository $ratingRepository, TripRepo $tripRepo)
    {
        $this->ratingRepository = $ratingRepository;
        $this->tripRepo = $tripRepo;
    }

    public function validator(array $data)
    {
        return Validator::make($data, [
            'comment' => 'required|string',
            'rating' => 'required|integer|in:0,1,',
        ]);
    } 

    public function getRate($userOrHash, $user_to_id, $trip_id)
    {
        if ($userOrHash instanceof User) {
            $rate = $this->ratingRepository->getRating($userOrHash->id, $user_to_id, $trip_id);
        } else if (is_int($userOrHash)) {
            $rate = $this->ratingRepository->getRating($userOrHash, $user_to_id, $trip_id);
        } else {
            $rate = $this->ratingRepository->findBy('voted_hash', $userOrHash)
                         ->where('user_to_id', $user_to_id)
                         ->where('trip_id', $trip_id)
                         ->first();
        }
        if (!$rate->voted && $rate->created_at->addDays(Rating::RATING_INTERVAL)->gte(Carbon::now())) {

            return $rate;
        }

        return;
    }
    
    public function getRatings ($user, $data = []) 
    {

        return $this->ratingRepository->getRatings($user, $data);
    }

    public function getPendingRatings ($user) 
    {

        return $this->ratingRepository->getPendingRatings($user);
    } 

    public function getPendingRatingsByHash ($hash) 
    {
        $response = [];
        $rates = $this->ratingRepository->findBy('voted_hash', $hash);
        foreach($rates as $rate) {
            if (!$rate->voted && $rate->created_at->addDays(Rating::RATING_INTERVAL)->gte(Carbon::now())) {
                $response[] = $rate;
            }
        }

        return new Collection($response);
    }

    public function rateUser ($user_from, $user_to_id, $trip_id, $data)
    {
        $v = $this->validator($data);
        if ($v->fails()) {
            $this->setErrors($v->errors());

            return;
        }

        if ($rate = $this->getRate($user_from, $user_to_id, $trip_id)) {
            $rate->voted = true;
            $rate->comment = $data['comment'];
            $rate->voted_hash = "";
            $rate->rate_at = Carbon::now();
            $rate->rating = parse_boolean($data['rating']) ? Rating::STATE_POSITIVO : Rating::STATE_NEGATIVO;

            return $this->ratingRepository->update($rate);    
        } else {
            $this->setErrors(['error' => 'user_have_already_voted']);

            return;
        }
    }
 
    public function replyRating ($user_from, $user_to_id, $trip_id, $comment)
    {
        $rate = $this->ratingRepository->getRating($user_to_id, $user_from->id, $trip_id);
        if ($rate && !$rate->reply_comment_created_at) {
            $rate->reply_comment_created_at = Carbon::now();
            $rate->reply_comment = $comment;

            return $this->ratingRepository->update($rate);
        } else {
            $this->setErrors(['error' => 'user_have_already_replay']);

            return;
        }
    }


    public function activeRatings($when)
    {   
        $criterias = [
            'DATE(trip_date)' => $when,
            'mail_send' => false
        ];
        $trips = $this->tripRepo->index($criterias, ['user', 'passenger']);

        foreach ($trips as $trip) {
            $driver = $trip->user;
            $driver_hash = str_random(40);

            foreach ($trip->passenger as $passenger) {
                if ($passenger->request_state == Passenger::STATE_ACCEPTED || $passenger->request_state == Passenger::STATE_CANCELED) {

                    $passenger_hash = str_random(40);
                    $rate = $this->ratingRepository->create($driver->id, $passenger->user_id, $trip->id, Passenger::TYPE_PASAJERO, $passenger->request_state, $driver_hash);                    
                    event(new PendingEvent($rate));

                    $rate = $this->ratingRepository->create($passenger->user_id, $driver->id, $trip->id, Passenger::TYPE_CONDUCTOR, Passenger::STATE_ACCEPTED, $passenger_hash);
                    event(new PendingEvent($rate));
                }
            }

            $this->tripRepo->update($trip, ['mail_send' => true]);
        }


        return ;
    }
    
    
 }