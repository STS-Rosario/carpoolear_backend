<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use Dingo\Api\Exception\ResourceException;
use STS\Contracts\Logic\IRateLogic;
use Dingo\Api\Exception\StoreResourceFailedException;
use Dingo\Api\Exception\UpdateResourceFailedException;

class RatingController extends Controller
{
    protected $userLogic;

    public function __construct(IRateLogic $rateLogic)
    {
        $this->middleware('api.auth', ['except' => ['pendingRate']]);
        $this->rateLogic = $rateLogic;
    }

    public function ratings(Request $request)
    {
        $data = $request->all();

        $me = $this->auth->user();
        
        $data = $this->rateLogic->getRatings($me, $data);

        return $this->response->withArray(['data' => $data]);
    }

    public function pendingRate(Request $request)
    {
        $data = $request->all();

        $me = $this->auth->user();
        if ($me) {
            $data = $this->rateLogic->getPendingRatings($me);
        } else {
            if ($request->has('hash')) {
                $hash = $request->has('hash');
                $data = $this->rateLogic->getPendingRatings($hash);
            } else {
                throw new BadRequestHttpException('Hash not provided');
            }
        }
        
        return $this->response->withArray(['data' => $data]);
    }

    public function rate($tripId, $userId, Request $request)
    {
        $me = $this->auth->user();

        $response = $this->rateLogic->rateUser($me, $userId, $tripId, $request->all());

        if (! $response) {
            throw new UpdateResourceFailedException('Could not rate user.', $this->rateLogic->getErrors());
        }

        return $this->response->withArray(['data' => 'ok']);
    }

    public function replay($tripId, $userId, Request $request)
    {
        $me = $this->auth->user();

        $comment = $request->get('comment');

        $response = $this->rateLogic->replyRating($me, $userId, $tripId, $comment);

        if (! $response) {
            throw new UpdateResourceFailedException('Could not replay user.', $this->rateLogic->getErrors());
        }

        return $this->response->withArray(['data' => 'ok']);
    } 
}
