<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Http\ExceptionWithErrors;
use STS\Services\Logic\TripLiveShareManager;
use STS\Transformers\TripLiveShareTransformer;

class TripLiveShareController extends Controller
{
    public function __construct(
        protected TripLiveShareManager $liveShareManager
    ) {
        $this->middleware('logged')->except(['publicView']);
        $this->middleware('logged.optional')->only('publicView');
    }

    public function start(int $tripId)
    {
        $user = auth()->user();
        $share = $this->liveShareManager->start($user, $tripId);
        if (! $share) {
            throw new ExceptionWithErrors('Could not start live location sharing.', $this->liveShareManager->getErrors());
        }

        return $this->item($share, new TripLiveShareTransformer);
    }

    public function updateLocation(int $tripId, Request $request)
    {
        $user = auth()->user();
        $data = $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $share = $this->liveShareManager->updateLocation($user, $tripId, (float) $data['lat'], (float) $data['lng']);
        if (! $share) {
            throw new ExceptionWithErrors('Could not update live location.', $this->liveShareManager->getErrors());
        }

        return $this->item($share, new TripLiveShareTransformer);
    }

    public function stop(int $tripId)
    {
        $user = auth()->user();
        $share = $this->liveShareManager->stop($user, $tripId);
        if (! $share) {
            throw new ExceptionWithErrors('Could not stop live location sharing.', $this->liveShareManager->getErrors());
        }

        return $this->item($share, new TripLiveShareTransformer);
    }

    public function status(int $tripId)
    {
        $user = auth()->user();
        $share = $this->liveShareManager->getStatus($user, $tripId);
        if ($this->liveShareManager->getErrors()) {
            throw new ExceptionWithErrors('Could not get live share status.', $this->liveShareManager->getErrors());
        }

        if (! $share) {
            return response()->json(['data' => null]);
        }

        return $this->item($share, new TripLiveShareTransformer);
    }

    public function tripView(int $tripId)
    {
        $user = auth()->user();
        $view = $this->liveShareManager->getTripView($user, $tripId);
        if ($this->liveShareManager->getErrors()) {
            throw new ExceptionWithErrors('Could not view live location.', $this->liveShareManager->getErrors());
        }

        if (! $view) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $view]);
    }

    public function publicView(string $token)
    {
        $view = $this->liveShareManager->getPublicView($token);
        if (! $view) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json(['data' => $view]);
    }
}
