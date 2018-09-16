<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Transformers\ProfileTransformer;
use Dingo\Api\Exception\ResourceException;
use STS\Contracts\Logic\User as UserLogic;
use Dingo\Api\Exception\StoreResourceFailedException;
use Dingo\Api\Exception\UpdateResourceFailedException;
use STS\Entities\Donation;

class UserController extends Controller
{
    protected $userLogic;

    public function __construct(UserLogic $userLogic)
    {
        $this->middleware('logged', ['except' => ['create', 'registerDonation']]);
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
        //return $this->item($user, new ProfileTransformer($me), ['key' => 'user']);
    }

    public function update(Request $request)
    {
        $me = $this->auth->user();
        $data = $request->all();
        if (isset($data['email'])) {
            unset($data['email']);
        }
        $profile = $this->userLogic->update($me, $data);
        if (! $profile) {
            throw new UpdateResourceFailedException('Could not update user.', $this->userLogic->getErrors());
        }

        return $this->item($profile, new ProfileTransformer($me), ['key' => 'user']);
    }

    public function updatePhoto(Request $request)
    {
        $me = $this->auth->user();
        $profile = $this->userLogic->updatePhoto($me, $request->all());
        if (! $profile) {
            throw new  UpdateResourceFailedException('Could not update user.', $this->userLogic->getErrors());
        }

        return $this->item($profile, new ProfileTransformer($me), ['key' => 'user']);
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

    public function registerDonation (Request $request) 
    {
        $donation = new Donation();
        if ($request->has('has_donated')) {
            $donation->has_donated = $request->get('has_donated');
        }
        if ($request->has('has_denied')) {
            $donation->has_denied = $request->get('has_denied');
        }
        if ($request->has('ammount')) {
            $donation->ammount = $request->get('ammount');
        }
        $user = null;
        if ($request->has('user')) {
            $user = new \stdClass();
            $user->id = intval($request->get('user'));
            if (!$user->id > 0) {
                $user->id = 164619; //donador anonimo
            }
        } else {
            $user = $this->user;
        }
        $this->userLogic->registerDonation($user, $donation);
    }
}
