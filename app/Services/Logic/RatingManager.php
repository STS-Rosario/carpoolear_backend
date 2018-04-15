<?php

namespace STS\Services\Logic;

use STS\User;
use Validator;
use Carbon\Carbon;
use STS\Entities\Rating;
use STS\Entities\Passenger;
use STS\Contracts\Logic\IRateLogic;
use Illuminate\Database\Eloquent\Collection;
use STS\Contracts\Repository\Trip as TripRepo;
use STS\Contracts\Repository\IRatingRepository;
use STS\Events\Rating\PendingRate as PendingEvent;

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
            'comment' => 'string',
            'rating' => 'required|integer|in:0,1,',
        ]);
    }

    public function getRate($userOrHash, $user_to_id, $trip_id)
    {
        if ($userOrHash instanceof User) {
            $rate = $this->ratingRepository->getRating($userOrHash->id, $user_to_id, $trip_id);
        } elseif (is_int($userOrHash)) {
            $rate = $this->ratingRepository->getRating($userOrHash, $user_to_id, $trip_id);
        } else {
            $rate = $this->ratingRepository->findBy('voted_hash', $userOrHash)
                         ->where('user_to_id', $user_to_id)
                         ->where('trip_id', $trip_id)
                         ->where('created_at', '>=', Carbon::Now()->subDays(Rating::RATING_INTERVAL))
                         ->first();
        }
        if (!$rate->voted && $rate->created_at->addDays(Rating::RATING_INTERVAL)->gte(Carbon::now())) {
            return $rate;
        }
    }

    public function getRatings($user, $data = [])
    {
        return $this->ratingRepository->getRatings($user, $data);
    }

    public function getPendingRatings($user)
    {
        return $this->ratingRepository->getPendingRatings($user);
    }

    public function getPendingRatingsByHash($hash)
    {
        $response = [];
        $rates = $this->ratingRepository->findBy('voted_hash', $hash);
        foreach ($rates as $rate) {
            if (! $rate->voted && $rate->created_at->addDays(Rating::RATING_INTERVAL)->gte(Carbon::now())) {
                $response[] = $rate;
            }
        }

        return new Collection($response);
    }

    public function rateUser($user_from, $user_to_id, $trip_id, $data)
    {
        $v = $this->validator($data);
        if ($v->fails()) {
            $this->setErrors($v->errors());

            return;
        }

        if ($rate = $this->getRate($user_from, $user_to_id, $trip_id)) {
            $rate->voted = true;
            $rate->comment = $data['comment'];
            $rate->voted_hash = '';
            $rate->rate_at = Carbon::now();
            $rate->rating = parse_boolean($data['rating']) ? Rating::STATE_POSITIVO : Rating::STATE_NEGATIVO;

            $result = $this->ratingRepository->update($rate);
            $this->ratingRepository->update_rating_availability($rate);
            return $result;
        } else {
            $this->setErrors(['error' => 'user_have_already_voted']);

            return;
        }
    }

    public function replyRating($user_from, $user_to_id, $trip_id, $comment)
    {
        $rate = $this->ratingRepository->getRating($user_to_id, $user_from->id, $trip_id);
        if ($rate && ! $rate->reply_comment_created_at) {
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
            ['key' => 'trip_date', 'value' => $when, 'op' => '<'],
            ['key' => 'mail_send', 'value' => false],
            ['key' => 'is_passenger', 'value' => false],
        ];


        $trips = $this->tripRepo->index($criterias, ['user', 'passenger']);

        foreach ($trips as $trip) {
            $driver = $trip->user;
            $driver_hash = str_random(40);
            $has_passenger = false;

            $passengers = $trip->passenger()->orderBy('created_at', 'desc')->get();

            $passenger_ids_rates_created = [];

            foreach ($passengers as $passenger) {

                $inRatingState = $passenger->request_state == Passenger::STATE_ACCEPTED || $passenger->request_state == Passenger::STATE_CANCELED;

                $canceledButAccepted = true;
                if ($passenger->request_state == Passenger::STATE_CANCELED) {
                    if (isset($passenger->canceled_state) && $passenger->canceled_state === Passenger::CANCELED_REQUEST) {
                        $canceledButAccepted = false;
                    }
                }

                if ($inRatingState && $canceledButAccepted) {
                    // the passenger could be make more than one trip request
                    if (!in_array($passenger->user->id, $passenger_ids_rates_created)) {
                        $passenger_hash = str_random(40);
                        $rate = $this->ratingRepository->create($driver->id, $passenger->user_id, $trip->id, Passenger::TYPE_PASAJERO, $passenger->request_state, $driver_hash);

                        $rate = $this->ratingRepository->create($passenger->user_id, $driver->id, $trip->id, Passenger::TYPE_CONDUCTOR, Passenger::STATE_ACCEPTED, $passenger_hash);
                        $has_passenger = true;
                        event(new PendingEvent($passenger->user, $trip, $passenger_hash));

                        $passenger_ids_rates_created[] = $passenger->user->id;
                    }
                }
            }
            if ($has_passenger) {
                event(new PendingEvent($driver, $trip, $driver_hash));
            }
            $this->tripRepo->update($trip, ['mail_send' => true]);
        }
    }
}
