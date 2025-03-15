<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request; 
use STS\Http\Controllers\Controller;
use STS\Http\ExceptionWithErrors;
use STS\Services\Logic\RatingManager;
use STS\Services\Logic\UsersManager;
use STS\Transformers\RatingTransformer;

class RatingController extends Controller
{
    protected $userLogic;

    protected $rateLogic;

    public function __construct(RatingManager $rateLogic, UsersManager $userLogic)
    {
        $this->middleware('logged:optional', ['except' => ['rate']]);
        $this->rateLogic = $rateLogic;
        $this->userLogic = $userLogic;
    }

    public function ratings($id = null)
    {
        $data = request()->all();

        $me = auth()->user();
        $user = null;
        if (is_null($id) || $me->id == $id) {
            $user = $me;
        } else {
            $user = $this->userLogic->show($me, $id);
        }

        if (! $user) {
            throw new ExceptionWithErrors('Users not found.', $this->userLogic->getErrors());
        }

        $data = $this->rateLogic->getRatings($user, $data);

        return $this->paginator($data, new RatingTransformer());
    }

    public function pendingRate(Request $request)
    {
        $data = $request->all();

        $me = auth()->user();
        if ($me) {
            $data = $this->rateLogic->getPendingRatings($me);
        } else {
            if ($request->has('hash')) {
                $hash = $request->has('hash');
                $data = $this->rateLogic->getPendingRatings($hash);
            } else {
                throw new ExceptionWithErrors('Hash not provided');
            }
        }

        return $this->collection($data, new RatingTransformer());
    }

    public function rate($tripId, $userId, Request $request)
    {
        $me = auth()->user();

        if ($me) {
            $response = $this->rateLogic->rateUser($me, $userId, $tripId, $request->all());
        } else {
            if ($request->has('hash')) {
                $hash = $request->has('hash');
                $response = $this->rateLogic->rateUser($me, $hash, $tripId, $request->all());
            } else {
                throw new ExceptionWithErrors('Hash not provided');
            }
        }

        if (! $response) {
            throw new ExceptionWithErrors('Could not rate user.', $this->rateLogic->getErrors());
        }

        return response()->json(['data' => 'ok']);
    }

    public function replay($tripId, $userId, Request $request)
    {
        $me = auth()->user();

        $comment = $request->get('comment');

        $response = $this->rateLogic->replyRating($me, $userId, $tripId, $comment);

        if (! $response) {
            throw new ExceptionWithErrors('Could not replay user.', $this->rateLogic->getErrors());
        }

        return response()->json(['data' => 'ok']);
    }
}
