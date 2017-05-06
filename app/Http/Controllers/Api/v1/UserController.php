<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Transformers\ProfileTransformer;
use Dingo\Api\Exception\ResourceException;
use STS\Contracts\Logic\User as UserLogic;
use Dingo\Api\Exception\StoreResourceFailedException;
use Dingo\Api\Exception\UpdateResourceFailedException;

class UserController extends Controller
{
    protected $userLogic;

    public function __construct(UserLogic $userLogic)
    {
        $this->middleware('api.auth', ['except' => ['create']]);
        $this->userLogic = $userLogic;
    }

    public function create(Request $request)
    {
        $data = $request->all();
        $user = $this->userLogic->create($data);
        if (! $user) {
            throw new StoreResourceFailedException('Could not create new user.', $this->userLogic->getErrors());
        }

        return $this->response->withArray(['user' => $user]);
    }

    public function update(Request $request)
    {
        $me = $this->auth->user();
        $profile = $this->userLogic->update($me, $request->all());
        if (! $profile) {
            throw new UpdateResourceFailedException('Could not update user.', $this->userLogic->getErrors());
        }

        return $this->response->withArray(['user' => $profile]);
    }

    public function updatePhoto(Request $request)
    {
        $me = $this->auth->user();
        $profile = $this->userLogic->updatePhoto($me, $request->all());
        if (! $profile) {
            throw new  UpdateResourceFailedException('Could not update user.', $this->userLogic->getErrors());
        }

        return $this->response->withArray(['user' => $profile]);
    }

    public function show($name = null)
    {
        $me = $this->auth->user();
        if (! $name) {
            $name = $me->id;
        }
        $profile = $this->userLogic->show($me, $name);
        if (! $profile) {
            throw new ResourceException('Users not found.', $this->userLogic->getErrors());
        }

        return $this->item($profile, new ProfileTransformer($me), ['key' => 'user']);
        //return $this->response->withArray(['user' => $profile]);
    }

    public function index(Request $request)
    {
        $search_text = null;
        if ($request->has('value')) {
            $search_text = $request->get('value');
        }
        $users = $this->userLogic->index($this->user, $search_text);

        return $this->collection($users, new ProfileTransformer($this->user));
    }
}
