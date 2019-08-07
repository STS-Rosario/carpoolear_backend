<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Contracts\Logic\IRateLogic;
use STS\Http\Controllers\Controller;
use STS\Transformers\RatingTransformer;
use Dingo\Api\Exception\ResourceException;
use STS\Contracts\Logic\User as UserLogic;
use Dingo\Api\Exception\UpdateResourceFailedException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class RatingController extends Controller
{
    protected $userLogic;

    protected $rateLogic;

    public function __construct(IRateLogic $rateLogic, UserLogic $userLogic)
    {
        $this->middleware('logged', ['except' => ['pendingRate', 'rate']]);
        $this->rateLogic = $rateLogic;
        $this->userLogic = $userLogic;
    }

    public function ratings($id = null)
    {
        $data = request()->all();

        $me = $this->auth->user();
        $user = null;
        if (is_null($id) || $me->id == $id) {
            $user = $me;
        } else {
            $user = $this->userLogic->show($me, $id);
        }

        if (! $user) {
            throw new ResourceException('Users not found.', $this->userLogic->getErrors());
        }

        $data = $this->rateLogic->getRatings($user, $data);

        return $this->response->paginator($data, new RatingTransformer());
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

        return $this->response->collection($data, new RatingTransformer());
    }

    public function rate($tripId, $userId, Request $request)
    {
        $me = $this->auth->user();

        if ($me) {
            $response = $this->rateLogic->rateUser($me, $userId, $tripId, $request->all());
        } else {
            if ($request->has('hash')) {
                $hash = $request->has('hash');
                $response = $this->rateLogic->rateUser($me, $hash, $tripId, $request->all());
            } else {
                throw new BadRequestHttpException('Hash not provided');
            }
        }

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
