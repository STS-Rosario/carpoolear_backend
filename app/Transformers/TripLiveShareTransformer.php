<?php

namespace STS\Transformers;

use League\Fractal\TransformerAbstract;
use STS\Models\TripLiveShare;

class TripLiveShareTransformer extends TransformerAbstract
{
    public function transform(TripLiveShare $share)
    {
        return [
            'id' => $share->id,
            'trip_id' => $share->trip_id,
            'user_id' => $share->user_id,
            'share_token' => $share->share_token,
            'is_active' => (bool) $share->is_active,
            'lat' => $share->lat,
            'lng' => $share->lng,
            'recorded_at' => $share->recorded_at?->toIso8601String(),
            'started_at' => $share->started_at?->toIso8601String(),
            'stopped_at' => $share->stopped_at?->toIso8601String(),
        ];
    }
}
