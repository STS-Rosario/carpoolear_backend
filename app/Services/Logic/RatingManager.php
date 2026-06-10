<?php

namespace STS\Services\Logic;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use STS\Events\Rating\PendingRate as PendingEvent;
use STS\Helpers\RatingHelper;
use STS\Models\Passenger;
use STS\Models\Rating;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\RatingRepository;
use STS\Repository\TripRepository;
use Validator;

class RatingManager extends BaseManager
{
    protected $ratingRepository;

    protected $tripRepo;

    public function __construct(RatingRepository $ratingRepository, TripRepository $tripRepo)
    {
        $this->ratingRepository = $ratingRepository;
        $this->tripRepo = $tripRepo;
    }

    public function validator(array $data)
    {
        return Validator::make($data, [
            'comment' => 'nullable|string',
            'rating' => 'required|integer|in:0,1,2',
        ]);
    }

    public function getRate($userOrHash, $user_to_id, $trip_id)
    {
        if ($userOrHash instanceof User) {
            $rate = $this->ratingRepository->getRating($userOrHash->id, $user_to_id, $trip_id);
        } elseif (is_int($userOrHash)) {
            $rate = $this->ratingRepository->getRating($userOrHash, $user_to_id, $trip_id);
        } else {
            $rate = Rating::query()
                ->where('voted_hash', $userOrHash)
                ->where('user_id_to', $user_to_id)
                ->where('trip_id', $trip_id)
                ->where('created_at', '>=', Carbon::now()->subDays(Rating::RATING_INTERVAL))
                ->first();
        }
        if (! $rate) {
            return null;
        }
        if (! $rate->voted && $rate->created_at->addDays(Rating::RATING_INTERVAL)->gte(Carbon::now())) {
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
            $rate->rating = (int) $data['rating'];

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
        $this->createEligibleRatings();
        $this->sendRatingNotifications($when);
    }

    public function createEligibleRatings(): void
    {
        $now = Carbon::now();
        $trips = Trip::query()
            ->where('is_passenger', false)
            ->where('mail_send', false)
            ->where('trip_date', '<', $now)
            ->whereDoesntHave('ratings')
            ->with(['user', 'passenger'])
            ->get();

        foreach ($trips as $trip) {
            if (! RatingHelper::isRatingAvailable($now, Carbon::parse($trip->trip_date), $trip->estimated_time)) {
                continue;
            }

            $this->createRatingsForTrip($trip);
        }
    }

    public function sendRatingNotifications($when): void
    {
        $trips = $this->tripRepo->index($this->driverTripCriteria([
            ['key' => 'trip_date', 'value' => $when, 'op' => '<'],
            ['key' => 'mail_send', 'value' => false],
        ]), ['user', 'passenger']);

        foreach ($trips as $trip) {
            if (! Rating::query()->where('trip_id', $trip->id)->exists()) {
                continue;
            }

            $this->dispatchRatingNotificationsForTrip($trip);
            $this->tripRepo->update($trip, ['mail_send' => true]);
        }
    }

    private function createRatingsForTrip(Trip $trip): bool
    {
        $driver = $trip->user;
        $driver_hash = Str::random(40);
        $has_passenger = false;
        $passengers = $trip->passenger()->orderBy('created_at', 'desc')->get();
        $passenger_ids_rates_created = [];

        foreach ($passengers as $passenger) {
            if (! $passenger->isEligibleForRating()) {
                continue;
            }

            if (in_array($passenger->user->id, $passenger_ids_rates_created)) {
                continue;
            }

            $passenger_hash = Str::random(40);
            $this->ratingRepository->create($driver->id, $passenger->user_id, $trip->id, Passenger::TYPE_PASAJERO, $passenger->request_state, $driver_hash);
            $this->ratingRepository->create($passenger->user_id, $driver->id, $trip->id, Passenger::TYPE_CONDUCTOR, Passenger::STATE_ACCEPTED, $passenger_hash);
            $has_passenger = true;
            $passenger_ids_rates_created[] = $passenger->user->id;
        }

        return $has_passenger;
    }

    private function dispatchRatingNotificationsForTrip(Trip $trip): void
    {
        $notifiedUserIds = [];

        $pendingRatings = Rating::query()
            ->where('trip_id', $trip->id)
            ->where('voted', false)
            ->with('from')
            ->get();

        foreach ($pendingRatings as $rate) {
            if (in_array($rate->user_id_from, $notifiedUserIds)) {
                continue;
            }

            event(new PendingEvent($rate->from, $trip, $rate->voted_hash));
            $notifiedUserIds[] = $rate->user_id_from;
        }
    }

    /**
     * @param  list<array{key: string, value: mixed, op?: string}>  $extra
     * @return list<array{key: string, value: mixed, op?: string}>
     */
    private function driverTripCriteria(array $extra = []): array
    {
        return array_merge([
            ['key' => 'is_passenger', 'value' => false],
        ], $extra);
    }
}
