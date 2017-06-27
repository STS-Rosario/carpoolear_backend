<?php

namespace STS\Transformers;

use STS\Entities\Rating;
use League\Fractal\TransformerAbstract;

class RatingTransformer extends TransformerAbstract
{
    protected $user;

    public function __construct($user = null)
    {
        $this->user = $user;
    }

    /**
     * Turn this item object into a generic array.
     *
     * @return array
     */
    public function transform(Rating $rate)
    {
        $tripTrans = new TripTransformer($this->user);
        $userTrans = new TripUserTransformer($this->user);

        $data = [
            'id' => $rate->id,
            'from' => $userTrans->transform($rate->from),
            // 'to' => $userTrans->transform($rate->to),
            'trip' => $tripTrans->transform($rate->trip),
            'comment' => $rate->comment,
            'user_to_state' => $rate->user_to_state,
            'user_to_type' => $rate->user_to_type,
            'rate_at' => $rate->rate_at ? $rate->rate_at->toDateTimeString() : null,
            'replay_comment' => $rate->replay_comment,
            'reply_comment_created_at' => $rate->reply_comment_created_at ? $rate->reply_comment_created_at->toDateTimeString() : null,
            'rating' => $rate->rating,
        ];
        if (! $rate->rating) {
            $data['from'] = $userTrans->transform($rate->from);
        }

        return $data;
    }
}
