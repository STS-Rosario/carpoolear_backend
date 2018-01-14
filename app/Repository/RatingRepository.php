<?php

namespace STS\Repository;

use Carbon\Carbon;
use STS\Entities\Rating as RatingModel;
use STS\Contracts\Repository\IRatingRepository;

class RatingRepository implements IRatingRepository
{
    public function getRating($user_from_id, $user_to_id, $trip_id)
    {
        $rate = RatingModel::where('user_id_from', $user_from_id);
        $rate->where('user_id_to', $user_to_id);
        $rate->where('trip_id', $trip_id);

        return $rate->first();
    }

    public function getRatings($user, $data = [])
    {
        // Calificaciones expiradas que fueron votdas por ambos
        $ratingsNotDue = RatingModel::where('user_id_to', $user->id);
        $ratingsNotDue->where('voted', true);
        $ratingsNotDue->where('created_at', '>', Carbon::Now()->subDays(RatingModel::RATING_INTERVAL));
        $ratingsNotDue->join('rating as r', function ($join) use ($user) {
            $join->on('rating.trip_id', '=', 'r.trip_id');
            $join->on('r.user_id_from', '=', $user->id);
            $join->on('rating.user_id_to', '=', 'r.user_id_from');
            $join->on('r.voted', '=', true);
        });


        // Calificaciones que ya expiraron para votar
        $ratings = RatingModel::where('user_id_to', $user->id);
        $ratings->where('voted', true);

        if (isset($data['value'])) {
            $value = parse_boolean($data['value']);
            $value = $value ? RatingModel::STATE_POSITIVO : RatingModel::STATE_NEGATIVO;
            $ratings->where('rating', $value);
            $ratingsNotDue->where('rating', $value);
        }

        $ratings->where('created_at', '<=', Carbon::Now()->subDays(RatingModel::RATING_INTERVAL));

        $ratings->orderBy('created_at', 'desc');
        
        // Union de las dos querys
        $ratings->union($ratingsNotDue);



        $pageNumber = isset($data['page']) ? $data['page'] : null;
        $pageSize = isset($data['page_size']) ? $data['page_size'] : null;

        return make_pagination($ratings, $pageNumber, $pageSize);
    }

    public function getPendingRatings($user)
    {
        $ratings = RatingModel::where('user_id_from', $user->id);
        $ratings->where('voted', false);
        $ratings->with(['from', 'to', 'trip']);
        $ratings->where('created_at', '>=', Carbon::Now()->subDays(RatingModel::RATING_INTERVAL));

        return $ratings->get();
    }

    public function find($id)
    {
        return RatingModel::find($id);
    }

    public function findBy($key, $value)
    {
        return RatingModel::where($key, $value)->get();
    }

    public function create($user_from_id, $user_to_id, $trip_id, $user_to_type, $user_to_state, $hash)
    {
        $newRating = [
            'trip_id' => $trip_id,
            'user_id_from' => $user_from_id,
            'user_id_to' => $user_to_id,
            'rating' => null,
            'comment' => '',
            'voted' => false,
            'reply_comment_created_at' => null,
            'reply_comment' => '',
            'voted_hash' => $hash,
            'user_to_type' => $user_to_type,
            'user_to_state' => $user_to_state,
            'rate_at' => null,
        ];

        $newRating = RatingModel::create($newRating);

        return $newRating;
    }

    public function update($rateModel)
    {
        return $rateModel->save();
    }
}
