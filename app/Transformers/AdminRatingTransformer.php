<?php

namespace STS\Transformers;

use League\Fractal\TransformerAbstract;
use STS\Models\Rating;

class AdminRatingTransformer extends TransformerAbstract
{
    protected $user;

    public function __construct($user = null)
    {
        $this->user = $user;
    }

    public function transform(Rating $rate): array
    {
        $tripTrans = new TripTransformer($this->user);
        $userTrans = new TripUserTransformer($this->user);

        return [
            'id' => $rate->id,
            'from' => $userTrans->transformOrMissing($rate->from, $rate->user_id_from),
            'to' => $userTrans->transformOrMissing($rate->to, $rate->user_id_to),
            'trip' => $tripTrans->transformOrMissing($rate->trip, $rate->trip_id),
            'comment' => $rate->comment,
            'user_to_state' => $rate->user_to_state,
            'user_to_type' => $rate->user_to_type,
            'rate_at' => $rate->rate_at ? $rate->rate_at->toDateTimeString() : null,
            'reply_comment' => $rate->reply_comment,
            'reply_comment_created_at' => $rate->reply_comment_created_at
                ? $rate->reply_comment_created_at->toDateTimeString()
                : null,
            'rating' => $rate->rating,
        ];
    }
}
